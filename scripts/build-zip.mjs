/**
 * Packages a distributable plugin zip.
 *
 * Two things this has to get right that a plain `zip -r` does not:
 *   1. The folder inside the zip must be named after the plugin slug, not the
 *      checkout directory, since WordPress derives the plugin folder from it.
 *   2. vendor/ must be reinstalled without dev packages, or phpunit and
 *      php-cs-fixer ship to users.
 */
import { execFileSync } from 'node:child_process'
import fs from 'node:fs'
import path from 'node:path'
import process from 'node:process'
import { fileURLToPath } from 'node:url'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const distDir = path.join(root, 'dist')

/** Only these are copied. An allowlist means .env or .git can never slip in. */
const INCLUDE = [
  'backend',
  'assets',
  'languages',
  'readme.txt',
  'LICENSE',
  'uninstall.php',
  'composer.json' // documents what vendor/ holds; staging needs it for composer install
]

/** Needed to install vendor/ inside the staging copy, removed before zipping. */
const BUILD_ONLY = ['composer.lock']

/** Never ship these, even if an allowlisted folder contains them. */
const FORBIDDEN = ['.env', '.git', 'node_modules', '.DS_Store']

const log = message => process.stdout.write(`${message}\n`)
const fail = message => {
  process.stderr.write(`\n✘ ${message}\n`)
  process.exit(1)
}

function readPluginHeader() {
  const candidates = fs
    .readdirSync(root)
    .filter(file => file.endsWith('.php') && fs.statSync(path.join(root, file)).isFile())

  for (const file of candidates) {
    const contents = fs.readFileSync(path.join(root, file), 'utf8').slice(0, 4096)
    const name = contents.match(/^\s*\*\s*Plugin Name:\s*(.+)$/m)
    if (!name) continue

    const version = contents.match(/^\s*\*\s*Version:\s*(.+)$/m)
    const domain = contents.match(/^\s*\*\s*Text Domain:\s*(.+)$/m)

    return {
      entry: file,
      name: name[1].trim(),
      version: version ? version[1].trim() : '0.0.0',
      slug: domain ? domain[1].trim() : path.basename(file, '.php')
    }
  }

  return null
}

function copyInto(source, destination) {
  fs.cpSync(source, destination, {
    recursive: true,
    filter: src => !FORBIDDEN.includes(path.basename(src))
  })
}

function directorySize(dir) {
  let bytes = 0
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name)
    bytes += entry.isDirectory() ? directorySize(full) : fs.statSync(full).size
  }

  return bytes
}

const mb = bytes => `${(bytes / 1024 / 1024).toFixed(2)} MB`

/** Removes a dependency's own tooling, leaving its code and licence intact. */
const CRUFT_DIRS = new Set(['.github', 'tests', 'test', '.circleci'])
const CRUFT_FILE = /^(\.php-cs-fixer.*|\.gitignore|\.gitattributes|\.editorconfig|phpunit\.xml.*|phpcs\.xml.*|psalm\.xml.*|\.travis\.yml|Makefile)$/

function trimVendorCruft(vendorDir, depth = 0) {
  if (!fs.existsSync(vendorDir) || depth > 4) return 0

  let removed = 0
  for (const entry of fs.readdirSync(vendorDir, { withFileTypes: true })) {
    const full = path.join(vendorDir, entry.name)

    if (entry.isDirectory()) {
      if (CRUFT_DIRS.has(entry.name)) {
        fs.rmSync(full, { recursive: true, force: true })
        removed += 1

        continue
      }

      removed += trimVendorCruft(full, depth + 1)
    } else if (CRUFT_FILE.test(entry.name)) {
      fs.rmSync(full, { force: true })
      removed += 1
    }
  }

  return removed
}

const plugin = readPluginHeader()
if (!plugin) fail('No plugin file with a "Plugin Name:" header found in the project root.')

log(`Packaging ${plugin.name} ${plugin.version} (slug: ${plugin.slug})`)

if (!fs.existsSync(path.join(root, 'assets'))) {
  fail('assets/ is missing. Run `pnpm build:free` first, or use `pnpm prod:free-zip`.')
}

// Stage under the slug so the zip expands to the right folder name.
const stageRoot = fs.mkdtempSync(path.join(distDir.replace(/dist$/, ''), '.scm-package-'))
const stage = path.join(stageRoot, plugin.slug)
fs.mkdirSync(stage, { recursive: true })

try {
  fs.copyFileSync(path.join(root, plugin.entry), path.join(stage, plugin.entry))

  for (const item of [...INCLUDE, ...BUILD_ONLY]) {
    const source = path.join(root, item)
    if (!fs.existsSync(source)) {
      if (INCLUDE.includes(item)) log(`  - skipped ${item} (not present)`)
      continue
    }

    copyInto(source, path.join(stage, item))
    log(`  + ${item}`)
  }

  // Production dependencies only.
  if (fs.existsSync(path.join(stage, 'composer.json'))) {
    log('  · installing composer dependencies without dev packages…')
    try {
      execFileSync(
        'composer',
        ['install', '--no-dev', '--optimize-autoloader', '--no-interaction', '--quiet'],
        { cwd: stage, stdio: 'inherit' }
      )
    } catch {
      fail('composer install failed in the staging copy; the zip would ship dev packages.')
    }

    for (const file of BUILD_ONLY) fs.rmSync(path.join(stage, file), { force: true })
  }

  for (const leftover of ['phpunit', 'friendsofphp', 'bin']) {
    const stray = path.join(stage, 'vendor', leftover)
    if (fs.existsSync(stray)) {
      fs.rmSync(stray, { recursive: true, force: true })
      log(`  - removed vendor/${leftover}`)
    }
  }

  // Dependencies ship their own CI config and test suites; users need neither.
  // LICENSE and README files are deliberately kept.
  const trimmed = trimVendorCruft(path.join(stage, 'vendor'))
  if (trimmed > 0) log(`  - trimmed ${trimmed} dev files from vendor/`)

  fs.mkdirSync(distDir, { recursive: true })
  const zipPath = path.join(distDir, `${plugin.slug}-${plugin.version}.zip`)
  fs.rmSync(zipPath, { force: true })

  execFileSync('zip', ['-r', '-q', '-9', zipPath, plugin.slug, '-x', '*.DS_Store'], {
    cwd: stageRoot
  })

  const entries = execFileSync('unzip', ['-Z1', zipPath], { encoding: 'utf8' })
    .split('\n')
    .filter(Boolean)

  const smuggled = entries.filter(entry =>
    FORBIDDEN.some(bad => entry.split('/').includes(bad))
  )
  if (smuggled.length > 0) fail(`Excluded files ended up in the zip: ${smuggled.join(', ')}`)

  log('')
  log(`✔ ${path.relative(root, zipPath)}`)
  log(`  ${entries.length} files, ${mb(fs.statSync(zipPath).size)} zipped (${mb(directorySize(stage))} unpacked)`)
  log(`  top-level folder: ${plugin.slug}/`)
} finally {
  fs.rmSync(stageRoot, { recursive: true, force: true })
}
