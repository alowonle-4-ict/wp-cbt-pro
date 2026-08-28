<?php

declare(strict_types=1);

namespace WPCBTPro\Candidates;

/**
 * Routes candidate photos through the standard WordPress media pipeline
 * (wp_handle_upload + media_handle_upload) so mime-type checks, storage
 * paths, and permissions all follow core behavior — no bespoke file writes.
 */
final class PhotoUploader
{
    private const ALLOWED_MIMES = ['jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

    /**
     * @param string $fieldName key into $_FILES
     * @return int|\WP_Error attachment ID on success
     */
    public function handleUpload(string $fieldName): int|\WP_Error
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- only ever called from CandidatesAdminController::handleSave(), which already ran check_admin_referer().
        if (empty($_FILES[$fieldName]['name'])) {
            return new \WP_Error('no_file', __('No photo was uploaded.', 'wp-cbt-pro'));
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $overrides = ['test_form' => false, 'mimes' => self::ALLOWED_MIMES];
        $attachmentId = media_handle_upload($fieldName, 0, [], $overrides);

        if (is_wp_error($attachmentId)) {
            return $attachmentId;
        }

        return (int) $attachmentId;
    }
}
