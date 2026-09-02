/**
 * Joins conditional class names.
 *
 * Written as a helper rather than inline template literals because
 * prettier-plugin-tailwindcss rewrites class strings and trims the leading
 * space off a `${cond ? ' extra' : ''}` fragment, silently welding it to the
 * previous class.
 */
export default function classNames(...parts: (false | null | string | undefined)[]): string {
  return parts.filter(Boolean).join(' ')
}
