<?php
/**
 * Email digest for one check run.
 *
 * @var array       $findings     open findings at or above the user's threshold
 * @var array       $resolved     critical findings that cleared in this run
 * @var array|null  $impaired     impaired-state details, if monitoring is impaired
 * @var array       $explanations finding id => ['what','why','check']
 * @var array       $pageLabels   target id => label
 * @var string      $adminUrl     link to the flight log
 */

if (!defined('ABSPATH')) {
    exit;
}

$severityColours = [
    'critical' => '#b32d2e',
    'warning'  => '#bd8600',
    'info'     => '#2271b1',
];
?>
<div style="font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.5;color:#1d2327;max-width:640px;">

    <?php if ($impaired) : ?>
        <div style="border-left:4px solid #bd8600;background:#fcf9e8;padding:12px 16px;margin-bottom:20px;">
            <strong><?php esc_html_e('Monitoring is impaired', 'seo-change-monitor'); ?></strong><br>
            <?php echo esc_html($impaired['reason']); ?><br>
            <span style="color:#646970;font-size:13px;">
                <?php
                printf(
                    /* translators: %s: date */
                    esc_html__('Since %s. Until this is fixed, no all-clear can be trusted.', 'seo-change-monitor'),
                    esc_html($impaired['since'])
                );
                ?>
            </span>
        </div>
    <?php endif; ?>

    <?php if (!empty($findings)) : ?>
        <p><?php esc_html_e('These changes were detected on your site:', 'seo-change-monitor'); ?></p>

        <?php foreach ($findings as $finding) : ?>
            <?php
            $colour      = $severityColours[$finding->severity] ?? '#2271b1';
            $explanation = $explanations[$finding->id] ?? null;
            $label       = $pageLabels[$finding->target_id] ?? null;
            $before      = json_decode((string) $finding->before_value, true);
            $after       = json_decode((string) $finding->after_value, true);
            $events      = json_decode((string) $finding->attributed_events, true) ?: [];
            ?>
            <div style="border:1px solid #dcdcde;border-left:4px solid <?php echo esc_attr($colour); ?>;padding:14px 16px;margin-bottom:16px;">
                <div style="text-transform:uppercase;font-size:11px;letter-spacing:.05em;color:<?php echo esc_attr($colour); ?>;font-weight:700;">
                    <?php echo esc_html($finding->severity); ?>
                </div>
                <div style="font-weight:600;margin:2px 0 6px;">
                    <?php echo esc_html($label ?: __('Site-wide', 'seo-change-monitor')); ?>
                </div>

                <?php if ($explanation) : ?>
                    <p style="margin:0 0 8px;"><?php echo esc_html($explanation['what']); ?></p>
                    <p style="margin:0 0 8px;color:#50575e;"><strong><?php esc_html_e('Why it matters:', 'seo-change-monitor'); ?></strong> <?php echo esc_html($explanation['why']); ?></p>
                    <p style="margin:0 0 8px;color:#50575e;"><strong><?php esc_html_e('What to check:', 'seo-change-monitor'); ?></strong> <?php echo esc_html($explanation['check']); ?></p>
                <?php endif; ?>

                <table style="font-size:13px;border-collapse:collapse;margin-top:6px;">
                    <tr>
                        <td style="color:#646970;padding:2px 12px 2px 0;vertical-align:top;"><?php esc_html_e('Before', 'seo-change-monitor'); ?></td>
                        <td style="word-break:break-all;"><?php echo esc_html(is_scalar($before['value'] ?? null) ? (string) $before['value'] : wp_json_encode($before['value'] ?? null)); ?></td>
                    </tr>
                    <tr>
                        <td style="color:#646970;padding:2px 12px 2px 0;vertical-align:top;"><?php esc_html_e('After', 'seo-change-monitor'); ?></td>
                        <td style="word-break:break-all;"><?php echo esc_html(is_scalar($after['value'] ?? null) ? (string) $after['value'] : wp_json_encode($after['value'] ?? null)); ?></td>
                    </tr>
                </table>

                <?php if (!empty($events)) : ?>
                    <p style="margin:10px 0 0;font-size:13px;color:#646970;">
                        <?php esc_html_e('Site events shortly before this change (they may be related, but we cannot be certain):', 'seo-change-monitor'); ?><br>
                        <?php foreach ($events as $event) : ?>
                            • <?php echo esc_html(str_replace('_', ' ', $event['type'])); ?>
                            <strong><?php echo esc_html($event['subject']); ?></strong>
                            <?php echo esc_html($event['at']); ?><br>
                        <?php endforeach; ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($resolved)) : ?>
        <div style="border:1px solid #dcdcde;border-left:4px solid #008a20;padding:14px 16px;margin-bottom:16px;">
            <div style="font-weight:600;margin-bottom:6px;"><?php esc_html_e('All clear', 'seo-change-monitor'); ?></div>
            <p style="margin:0 0 8px;"><?php esc_html_e('These critical problems are no longer present:', 'seo-change-monitor'); ?></p>
            <ul style="margin:0;padding-left:18px;">
                <?php foreach ($resolved as $finding) : ?>
                    <li>
                        <?php
                        $label       = $pageLabels[$finding->target_id] ?? __('Site-wide', 'seo-change-monitor');
                        $explanation = $explanations[$finding->id] ?? null;
                        echo esc_html($label);
                        if ($explanation) {
                            echo ': ' . esc_html($explanation['what']);
                        }
                        ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <p style="font-size:13px;color:#646970;">
        <a href="<?php echo esc_url($adminUrl); ?>" style="color:#2271b1;"><?php esc_html_e('Open the flight log', 'seo-change-monitor'); ?></a>
        &nbsp;·&nbsp;
        <?php esc_html_e('Sent by SEO Change Monitor. Change your alert settings in the plugin.', 'seo-change-monitor'); ?>
    </p>
</div>
