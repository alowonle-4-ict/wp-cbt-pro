<?php
/**
 * @var array<string,mixed>|null $candidate
 * @var array<string,string> $errors
 * @var bool $showInstitutionField
 * @var array<int,array<string,mixed>> $institutions
 * @var int|null $currentInstitutionId
 * @var string $action
 */
if (!defined('ABSPATH')) {
    exit;
}

// Redisplay-only fallback, reached only after handleSave() already ran check_admin_referer(); esc_attr() below covers output safety.
// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
$field = static fn (string $key, string $default = ''): string => esc_attr(wp_unslash($candidate[$key] ?? $_POST[$key] ?? $default));

$listUrl = add_query_arg(['page' => 'wpcbtpro-candidates'], admin_url('admin.php'));
$photoId = (int) ($candidate['photo_attachment_id'] ?? 0);
?>
<div class="wrap wpcbtpro-wrap">
    <h1><?php echo $action === 'edit' ? esc_html__('Edit Candidate', 'wp-cbt-pro') : esc_html__('Add Candidate', 'wp-cbt-pro'); ?></h1>

    <?php if ($errors !== []): ?>
        <div class="notice notice-error">
            <p><?php foreach ($errors as $error): ?><?php echo esc_html($error); ?><br><?php endforeach; ?></p>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="wpcbtpro-form">
        <?php wp_nonce_field('wpcbtpro_save_candidate', 'wpcbtpro_candidate_nonce'); ?>
        <input type="hidden" name="candidate_id" value="<?php echo esc_attr((string) ($candidate['id'] ?? 0)); ?>">

        <table class="form-table" role="presentation">
            <tr>
                <th><label for="wpcbtpro_photo"><?php esc_html_e('Photograph', 'wp-cbt-pro'); ?></label></th>
                <td>
                    <?php if ($photoId): ?>
                        <?php echo wp_get_attachment_image($photoId, [96, 96], false, ['class' => 'wpcbtpro-photo-preview']); ?>
                    <?php else: ?>
                        <span class="wpcbtpro-photo-preview wpcbtpro-photo-preview--empty" aria-hidden="true"></span>
                    <?php endif; ?>
                    <input type="file" id="wpcbtpro_photo" name="wpcbtpro_photo" accept="image/png,image/jpeg,image/webp">
                    <p class="description"><?php esc_html_e('Shown on the exam screen during identity checks (§7, §11).', 'wp-cbt-pro'); ?></p>
                </td>
            </tr>

            <?php if ($showInstitutionField): ?>
            <tr>
                <th><label for="institution_id"><?php esc_html_e('Institution', 'wp-cbt-pro'); ?></label></th>
                <td>
                    <select id="institution_id" name="institution_id" <?php echo $action === 'edit' ? 'disabled' : ''; ?>>
                        <?php foreach ($institutions as $institution): ?>
                            <option value="<?php echo esc_attr((string) $institution['id']); ?>"
                                <?php selected((int) ($candidate['institution_id'] ?? $currentInstitutionId), (int) $institution['id']); ?>>
                                <?php echo esc_html($institution['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($action === 'edit'): ?>
                        <p class="description"><?php esc_html_e('Institution cannot be changed after a candidate is created.', 'wp-cbt-pro'); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endif; ?>

            <tr>
                <th><label for="first_name"><?php esc_html_e('First name', 'wp-cbt-pro'); ?> <span class="required">*</span></label></th>
                <td><input type="text" id="first_name" name="first_name" class="regular-text" value="<?php echo $field('first_name'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $field() escapes via esc_attr(), see top of file ?>" required></td>
            </tr>
            <tr>
                <th><label for="last_name"><?php esc_html_e('Last name', 'wp-cbt-pro'); ?> <span class="required">*</span></label></th>
                <td><input type="text" id="last_name" name="last_name" class="regular-text" value="<?php echo $field('last_name'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $field() escapes via esc_attr(), see top of file ?>" required></td>
            </tr>
            <tr>
                <th><label for="email"><?php esc_html_e('Email', 'wp-cbt-pro'); ?></label></th>
                <td><input type="email" id="email" name="email" class="regular-text" value="<?php echo $field('email'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $field() escapes via esc_attr(), see top of file ?>"></td>
            </tr>
            <tr>
                <th><label for="phone"><?php esc_html_e('Phone', 'wp-cbt-pro'); ?></label></th>
                <td><input type="text" id="phone" name="phone" class="regular-text" value="<?php echo $field('phone'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $field() escapes via esc_attr(), see top of file ?>"></td>
            </tr>
            <tr>
                <th><label for="wp_user_id"><?php esc_html_e('Linked WordPress account', 'wp-cbt-pro'); ?></label></th>
                <td>
                    <?php
                    wp_dropdown_users([
                        'name' => 'wp_user_id',
                        'id' => 'wp_user_id',
                        'selected' => (int) ($candidate['wp_user_id'] ?? 0),
                        'show_option_none' => __('— Not linked —', 'wp-cbt-pro'),
                        'option_none_value' => '0',
                    ]);
                    ?>
                    <p class="description"><?php esc_html_e('The candidate logs in as this account to take exams.', 'wp-cbt-pro'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="department"><?php esc_html_e('Department', 'wp-cbt-pro'); ?></label></th>
                <td><input type="text" id="department" name="department" class="regular-text" value="<?php echo $field('department'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $field() escapes via esc_attr(), see top of file ?>"></td>
            </tr>
            <tr>
                <th><label for="class"><?php esc_html_e('Class', 'wp-cbt-pro'); ?></label></th>
                <td><input type="text" id="class" name="class" class="regular-text" value="<?php echo $field('class'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $field() escapes via esc_attr(), see top of file ?>"></td>
            </tr>
            <tr>
                <th><label for="registration_number"><?php esc_html_e('Registration number', 'wp-cbt-pro'); ?></label></th>
                <td><input type="text" id="registration_number" name="registration_number" class="regular-text" value="<?php echo $field('registration_number'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $field() escapes via esc_attr(), see top of file ?>"></td>
            </tr>
            <?php if ($action === 'edit'): ?>
            <tr>
                <th><label for="status"><?php esc_html_e('Status', 'wp-cbt-pro'); ?></label></th>
                <td>
                    <select id="status" name="status">
                        <?php foreach (['active' => __('Active', 'wp-cbt-pro'), 'suspended' => __('Suspended', 'wp-cbt-pro'), 'archived' => __('Archived', 'wp-cbt-pro')] as $value => $label): ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($candidate['status'] ?? 'active', $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <?php endif; ?>
            <?php if ($candidate !== null): ?>
            <tr>
                <th><?php esc_html_e('Candidate ID', 'wp-cbt-pro'); ?></th>
                <td><code><?php echo esc_html($candidate['candidate_ref']); ?></code></td>
            </tr>
            <?php endif; ?>
        </table>

        <?php submit_button($action === 'edit' ? __('Save Changes', 'wp-cbt-pro') : __('Add Candidate', 'wp-cbt-pro')); ?>
        <a href="<?php echo esc_url($listUrl); ?>" class="wpcbtpro-cancel-link"><?php esc_html_e('Cancel', 'wp-cbt-pro'); ?></a>
    </form>
</div>
