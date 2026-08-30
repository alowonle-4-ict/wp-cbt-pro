<?php

declare(strict_types=1);

namespace WPCBTPro\Import\Word;

/**
 * The import preview embeds Word-document images as inline data: URIs so
 * nothing is uploaded until the admin actually confirms the import (§6.3,
 * same "nothing committed until confirm" rule as the rest of this pipeline).
 * This runs only on confirm, for rows actually being imported: it turns each
 * data: URI still present in the confirmed HTML into a real WP attachment
 * and rewrites the HTML to point at that attachment's URL.
 */
final class EmbeddedImageUploader
{
    private const ALLOWED = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif'];

    public function persist(string $html, string $filenamePrefix): string
    {
        return (string) preg_replace_callback(
            '#data:(image/(?:png|jpeg|gif));base64,([A-Za-z0-9+/=]+)#',
            function (array $m) use ($filenamePrefix): string {
                return $this->upload($m[1], $m[2], $filenamePrefix) ?? $m[0];
            },
            $html
        );
    }

    private function upload(string $mime, string $base64, string $filenamePrefix): ?string
    {
        $binary = base64_decode($base64, true);
        if ($binary === false) {
            return null;
        }

        $filename = sanitize_file_name($filenamePrefix . '-' . wp_generate_password(8, false, false) . '.' . self::ALLOWED[$mime]);

        $upload = wp_upload_bits($filename, null, $binary);
        if (!empty($upload['error'])) {
            return null;
        }

        $attachmentId = wp_insert_attachment([
            'post_mime_type' => $mime,
            'post_title' => sanitize_file_name($filenamePrefix),
            'post_status' => 'inherit',
        ], $upload['file']);

        if (is_wp_error($attachmentId)) {
            return null;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_update_attachment_metadata($attachmentId, wp_generate_attachment_metadata((int) $attachmentId, $upload['file']));

        $url = wp_get_attachment_url((int) $attachmentId);
        return $url !== false ? $url : null;
    }
}
