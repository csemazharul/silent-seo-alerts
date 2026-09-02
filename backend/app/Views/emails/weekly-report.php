<?php
/**
 * White-label weekly report.
 *
 * @var array $data built by WeeklyReport::build()
 */

if (!defined('ABSPATH')) {
    exit;
}

$brand  = $data['brand'];
$accent = $brand['color'];
$tone   = ['critical' => '#b32d2e', 'warning' => '#bd8600', 'info' => '#2271b1'];
?>
<div style="font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.55;color:#1d2327;max-width:640px;margin:0 auto;">

    <div style="border-bottom:3px solid <?php echo esc_attr($accent); ?>;padding-bottom:14px;margin-bottom:22px;">
        <?php if ($brand['logo_url'] !== '') : ?>
            <img alt="<?php echo esc_attr($brand['name']); ?>" src="<?php echo esc_url($brand['logo_url']); ?>" style="max-height:44px;display:block;margin-bottom:10px;">
        <?php elseif ($brand['name'] !== '') : ?>
            <div style="font-size:18px;font-weight:700;color:<?php echo esc_attr($accent); ?>;"><?php echo esc_html($brand['name']); ?></div>
        <?php endif; ?>
        <div style="font-size:20px;font-weight:600;margin-top:4px;">
            <?php
            printf(
                /* translators: %s: site name */
                esc_html__('Weekly SEO report for %s', 'seo-change-monitor'),
                esc_html($data['site_name'])
            );
            ?>
        </div>
        <div style="color:#646970;font-size:13px;">
            <?php echo esc_html($data['period_start']); ?> &rarr; <?php echo esc_html($data['period_end']); ?>
        </div>
    </div>

    <?php if ($data['impaired']) : ?>
        <div style="border-left:4px solid #bd8600;background:#fcf9e8;padding:12px 16px;margin-bottom:20px;">
            <strong><?php esc_html_e('Monitoring was impaired during this period', 'seo-change-monitor'); ?></strong><br>
            <?php echo esc_html($data['impaired']['reason']); ?>
        </div>
    <?php endif; ?>

    <table style="width:100%;border-collapse:collapse;margin-bottom:24px;">
        <tr>
            <?php
            $tiles = [
                __('Pages watched', 'seo-change-monitor') => $data['pages_watched'],
                __('Checks run', 'seo-change-monitor')    => $data['checks_run'],
                __('Critical', 'seo-change-monitor')      => $data['counts']['critical'],
                __('Warnings', 'seo-change-monitor')      => $data['counts']['warning'],
            ];
            foreach ($tiles as $label => $value) :
                ?>
                <td style="border:1px solid #dcdcde;border-radius:8px;padding:12px;text-align:center;width:25%;">
                    <div style="font-size:24px;font-weight:600;"><?php echo esc_html((string) $value); ?></div>
                    <div style="font-size:12px;color:#646970;"><?php echo esc_html($label); ?></div>
                </td>
            <?php endforeach; ?>
        </tr>
    </table>

    <?php if ($data['all_quiet']) : ?>
        <div style="border:1px solid #dcdcde;border-left:4px solid #008a20;padding:16px;margin-bottom:20px;">
            <strong><?php esc_html_e('A quiet week. Nothing changed.', 'seo-change-monitor'); ?></strong>
            <p style="margin:6px 0 0;color:#50575e;">
                <?php esc_html_e('Every monitored page returned the same SEO output it had last week.', 'seo-change-monitor'); ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if (!empty($data['findings'])) : ?>
        <h3 style="font-size:15px;margin:0 0 10px;"><?php esc_html_e('What changed', 'seo-change-monitor'); ?></h3>
        <?php foreach ($data['findings'] as $finding) : ?>
            <?php $colour = $tone[$finding['severity']] ?? '#2271b1'; ?>
            <div style="border:1px solid #dcdcde;border-left:4px solid <?php echo esc_attr($colour); ?>;padding:12px 14px;margin-bottom:12px;">
                <div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;font-weight:700;color:<?php echo esc_attr($colour); ?>;">
                    <?php echo esc_html($finding['severity']); ?>
                </div>
                <div style="font-weight:600;margin:2px 0 6px;">
                    <?php echo esc_html($finding['page'] ?: __('Site-wide', 'seo-change-monitor')); ?>
                </div>
                <p style="margin:0 0 6px;"><?php echo esc_html($finding['explanation']['what']); ?></p>
                <p style="margin:0;color:#50575e;font-size:13px;"><?php echo esc_html($finding['explanation']['why']); ?></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($data['resolved'])) : ?>
        <h3 style="font-size:15px;margin:20px 0 10px;"><?php esc_html_e('Resolved this week', 'seo-change-monitor'); ?></h3>
        <ul style="margin:0;padding-left:18px;color:#50575e;">
            <?php foreach ($data['resolved'] as $finding) : ?>
                <li style="margin-bottom:4px;">
                    <?php echo esc_html($finding['page'] ?: __('Site-wide', 'seo-change-monitor')); ?>
                    : <?php echo esc_html($finding['explanation']['what']); ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($data['open_critical'] > 0) : ?>
        <div style="border:1px solid #b32d2e;background:#fcf0f1;padding:12px 14px;margin-top:20px;">
            <strong>
                <?php
                printf(
                    /* translators: %d: number of findings */
                    esc_html(
                        _n(
                            '%d critical issue is still open.',
                            '%d critical issues are still open.',
                            $data['open_critical'],
                            'seo-change-monitor'
                        )
                    ),
                    (int) $data['open_critical']
                );
                ?>
            </strong>
        </div>
    <?php endif; ?>

    <div style="margin-top:26px;padding-top:14px;border-top:1px solid #dcdcde;color:#646970;font-size:12px;">
        <?php if ($brand['footer'] !== '') : ?>
            <?php echo esc_html($brand['footer']); ?>
        <?php else : ?>
            <?php
            printf(
                /* translators: %s: site URL */
                esc_html__('Automated SEO monitoring report for %s.', 'seo-change-monitor'),
                esc_html($data['site_url'])
            );
            ?>
        <?php endif; ?>
    </div>
</div>
