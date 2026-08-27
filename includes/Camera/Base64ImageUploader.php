<?php

declare(strict_types=1);

namespace WPCBTPro\Camera;

/**
 * Camera captures arrive as a data: URI from a <canvas> frame, never a
 * multipart file upload — this decodes and stores them the same way any
 * other WordPress attachment is stored, but private by default, since
 * proctoring images are sensitive data (§39).
 */
final class Base64ImageUploader
{
    private const ALLOWED = ['image/jpeg' => 'jpg', 'image/png' => 'png'];

    public function upload(string $dataUri, string $filenamePrefix): int|\WP_Error
    {
        if (!preg_match('#^data:(image/(?:jpeg|png));base64,(.+)$#', $dataUri, $m)) {
            return new \WP_Error('wpcbtpro_invalid_image', __('Unsupported image format.', 'wp-cbt-pro'));
        }

        $mime = $m[1];
        $binary = base64_decode($m[2], true);
        if ($binary === false) {
            return new \WP_Error('wpcbtpro_invalid_image', __('Could not decode image data.', 'wp-cbt-pro'));
        }

        $filename = sanitize_file_name($filenamePrefix . '-' . time() . '.' . self::ALLOWED[$mime]);

        $upload = wp_upload_bits($filename, null, $binary);
        if (!empty($upload['error'])) {
            return new \WP_Error('wpcbtpro_upload_failed', (string) $upload['error']);
        }

        $attachmentId = wp_insert_attachment([
            'post_mime_type' => $mime,
            'post_title' => sanitize_file_name($filenamePrefix),
            'post_status' => 'private',
        ], $upload['file']);

        if (is_wp_error($attachmentId)) {
            return $attachmentId;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_update_attachment_metadata($attachmentId, wp_generate_attachment_metadata((int) $attachmentId, $upload['file']));

        return (int) $attachmentId;
    }
}
