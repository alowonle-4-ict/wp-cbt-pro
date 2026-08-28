<?php

declare(strict_types=1);

namespace WPCBTPro\Candidates;

if (!class_exists(\WP_List_Table::class)) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class CandidatesListTable extends \WP_List_Table
{
    private const PER_PAGE = 20;

    public function __construct(private readonly CandidateRepository $repository, private readonly ?int $institutionId)
    {
        parent::__construct([
            'singular' => 'candidate',
            'plural' => 'candidates',
            'ajax' => false,
        ]);
    }

    public function get_columns(): array
    {
        return [
            'photo' => '',
            'name' => __('Name', 'wp-cbt-pro'),
            'candidate_ref' => __('Candidate ID', 'wp-cbt-pro'),
            'registration_number' => __('Reg. Number', 'wp-cbt-pro'),
            'department' => __('Department / Class', 'wp-cbt-pro'),
            'status' => __('Status', 'wp-cbt-pro'),
        ];
    }

    protected function get_sortable_columns(): array
    {
        return [
            'name' => ['last_name', false],
            'candidate_ref' => ['candidate_ref', false],
            'status' => ['status', false],
        ];
    }

    public function prepare_items(): void
    {
        $search = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';
        $paged = max(1, isset($_REQUEST['paged']) ? absint($_REQUEST['paged']) : 1);
        $orderby = sanitize_key($_REQUEST['orderby'] ?? 'created_at');
        $order = sanitize_key($_REQUEST['order'] ?? 'desc');

        $args = [
            'institution_id' => $this->institutionId,
            'search' => $search,
            'per_page' => self::PER_PAGE,
            'page' => $paged,
            'orderby' => $orderby,
            'order' => $order,
        ];

        $this->items = $this->repository->paginate($args);
        $totalItems = $this->repository->count($args);

        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];
        $this->set_pagination_args([
            'total_items' => $totalItems,
            'per_page' => self::PER_PAGE,
            'total_pages' => (int) ceil($totalItems / self::PER_PAGE),
        ]);
    }

    public function column_photo(array $item): string
    {
        $photoId = (int) ($item['photo_attachment_id'] ?? 0);
        if ($photoId > 0) {
            return wp_get_attachment_image($photoId, [40, 40], false, ['class' => 'wpcbtpro-thumb']);
        }
        return '<span class="wpcbtpro-thumb wpcbtpro-thumb--placeholder" aria-hidden="true"></span>';
    }

    public function column_name(array $item): string
    {
        $editUrl = add_query_arg([
            'page' => 'wpcbtpro-candidates',
            'action' => 'edit',
            'id' => $item['id'],
        ], admin_url('admin.php'));

        $deleteUrl = wp_nonce_url(add_query_arg([
            'page' => 'wpcbtpro-candidates',
            'action' => 'delete',
            'id' => $item['id'],
        ], admin_url('admin.php')), 'wpcbtpro_delete_candidate_' . $item['id']);

        $name = esc_html(trim($item['first_name'] . ' ' . $item['last_name']));

        $actions = [
            'edit' => sprintf('<a href="%s">%s</a>', esc_url($editUrl), esc_html__('Edit', 'wp-cbt-pro')),
            'delete' => sprintf(
                '<a href="%s" onclick="return confirm(\'%s\');">%s</a>',
                esc_url($deleteUrl),
                esc_js(__('Remove this candidate? This cannot be undone.', 'wp-cbt-pro')),
                esc_html__('Remove', 'wp-cbt-pro')
            ),
        ];

        return sprintf('<strong><a href="%s">%s</a></strong>%s', esc_url($editUrl), $name, $this->row_actions($actions));
    }

    public function column_status(array $item): string
    {
        $status = $item['status'] ?? 'active';
        return sprintf('<span class="wpcbtpro-pill wpcbtpro-pill--%1$s">%2$s</span>', esc_attr($status), esc_html(ucfirst($status)));
    }

    public function column_department(array $item): string
    {
        return esc_html(trim(($item['department'] ?? '') . ' ' . ($item['class'] ? '· ' . $item['class'] : '')));
    }

    public function column_default($item, $column_name): string
    {
        return esc_html($item[$column_name] ?? '');
    }
}
