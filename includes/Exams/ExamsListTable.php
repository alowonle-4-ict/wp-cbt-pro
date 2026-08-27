<?php

declare(strict_types=1);

namespace WPCBTPro\Exams;

if (!class_exists(\WP_List_Table::class)) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class ExamsListTable extends \WP_List_Table
{
    private const PER_PAGE = 20;

    public function __construct(private readonly ExamRepository $repository, private readonly ?int $institutionId)
    {
        parent::__construct(['singular' => 'exam', 'plural' => 'exams', 'ajax' => false]);
    }

    public function get_columns(): array
    {
        return [
            'name' => __('Exam', 'wp-cbt-pro'),
            'subject' => __('Subject', 'wp-cbt-pro'),
            'duration_minutes' => __('Duration', 'wp-cbt-pro'),
            'schedule' => __('Schedule', 'wp-cbt-pro'),
            'status' => __('Status', 'wp-cbt-pro'),
        ];
    }

    public function prepare_items(): void
    {
        $search = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';
        $paged = max(1, (int) ($_REQUEST['paged'] ?? 1));

        $args = [
            'institution_id' => $this->institutionId,
            'search' => $search,
            'per_page' => self::PER_PAGE,
            'page' => $paged,
        ];

        $this->items = $this->repository->paginate($args);
        $totalItems = $this->repository->count($args);

        $this->_column_headers = [$this->get_columns(), [], []];
        $this->set_pagination_args([
            'total_items' => $totalItems,
            'per_page' => self::PER_PAGE,
            'total_pages' => (int) ceil($totalItems / self::PER_PAGE),
        ]);
    }

    public function column_name(array $item): string
    {
        $editUrl = add_query_arg(['page' => 'wpcbtpro-exams', 'action' => 'edit', 'id' => $item['id']], admin_url('admin.php'));
        $deleteUrl = wp_nonce_url(
            add_query_arg(['page' => 'wpcbtpro-exams', 'action' => 'delete', 'id' => $item['id']], admin_url('admin.php')),
            'wpcbtpro_delete_exam_' . $item['id']
        );

        $actions = [
            'edit' => sprintf('<a href="%s">%s</a>', esc_url($editUrl), esc_html__('Edit', 'wp-cbt-pro')),
            'delete' => sprintf(
                '<a href="%s" onclick="return confirm(\'%s\');">%s</a>',
                esc_url($deleteUrl),
                esc_js(__('Delete this exam? This cannot be undone.', 'wp-cbt-pro')),
                esc_html__('Delete', 'wp-cbt-pro')
            ),
        ];

        return sprintf(
            '<strong><a href="%s">%s</a></strong>%s',
            esc_url($editUrl),
            esc_html($item['name']),
            $this->row_actions($actions)
        );
    }

    public function column_duration_minutes(array $item): string
    {
        return esc_html(sprintf(
            /* translators: %d: exam duration in minutes */
            __('%d min', 'wp-cbt-pro'),
            (int) $item['duration_minutes']
        ));
    }

    public function column_schedule(array $item): string
    {
        if (empty($item['start_at'])) {
            return esc_html__('Not scheduled', 'wp-cbt-pro');
        }
        $start = mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $item['start_at']);
        return esc_html($start);
    }

    public function column_status(array $item): string
    {
        return sprintf(
            '<span class="wpcbtpro-pill wpcbtpro-pill--%1$s">%2$s</span>',
            esc_attr($item['status']),
            esc_html(ucfirst($item['status']))
        );
    }

    public function column_default($item, $column_name): string
    {
        return esc_html($item[$column_name] ?? '');
    }
}
