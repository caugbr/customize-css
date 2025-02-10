<?php

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Admin_Table extends WP_List_Table {
    private $rules_manager;

    public function __construct() {
        global $costumize_css;
        $this->rules_manager = $costumize_css;
        parent::__construct([
            'singular' => 'rule',
            'plural' => 'rules',
            'ajax' => false,
        ]);
    }

    /**
     * Define as colunas da tabela.
     */
    public function get_columns() {
        return [
            // 'cb' => '<input type="checkbox" />',
            'rule_name' => __("Rule name", "ccss"),
            'created_at' => __("Created at", "ccss"),
            'actions' => __("Actions", "ccss"),
        ];
    }

    /**
     * Colunas que podem ser ordenadas.
     */
    protected function get_sortable_columns() {
        return [
            'rule_name' => ['rule_name', true],  // Coluna, Default ordenação ascendente
            'created_at' => ['created_at', false],
        ];
    }

    /**
     * Preenche os dados da tabela.
     */
    public function prepare_items() {
        $per_page = 10;
        $data = $this->get_table_data();

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = array();
        $primary  = 'rule_name';
        $this->_column_headers = array($columns, $hidden, $sortable, $primary);

        // Ordenação
        $orderby = $_GET['orderby'] ?? 'rule_name';
        $order = $_GET['order'] ?? 'asc';
        usort($data, function ($a, $b) use($orderby, $order) {
            $result  = strcmp($a[$orderby], $b[$orderby]);
            return $order === 'asc' ? $result : -$result;
        });

        // Paginação
        $current_page = $this->get_pagenum();
        $total_items = count($data);
        $this->items = array_slice($data, ($current_page - 1) * $per_page, $per_page);
        
        $args = [
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ];
        $this->set_pagination_args($args);
    }

    /**
     * Dados para a tabela.
     */
    private function get_table_data() {
        $rules = $this->rules_manager->get_rules();
        $data = [];
        foreach ($rules as $rule) {
            $data[] = [
                'id' => $rule['id'],
                'rule_name' => $rule['rule_name'],
                'created_at' => $rule['created_at'],
                'actions' => $this->get_row_actions($rule['id']),
            ];
        }
        return $data;
    }

    /**
     * Gera os botões de ação para cada linha.
     */
    private function get_row_actions($id) {
        $edit_url = admin_url("admin.php?page=ccss-add-rule&rule=$id");
        $nonce = wp_create_nonce('ccss_nonce_action');
        $delete_url = admin_url("admin.php?page=ccss-rules&action=remove&rule={$id}&_wpnonce={$nonce}");

        return sprintf(
            '<a href="%s">%s</a> | <a href="%s" onclick="return confirm(\'%s\');">%s</a>',
            esc_url($edit_url),
            __("Edit", "ccss"),
            esc_url($delete_url),
            __("There is no undo. Are you sure?", "ccss"),
            __("Delete", "ccss")
        );
    }

    /**
     * Coluna padrão.
     */
    protected function column_default($item, $column_name) {
        return $item[$column_name] ?? '';
    }

    /**
     * Coluna de checkbox.
     */
    protected function column_cb($item) {
        return sprintf('<input type="checkbox" name="rule[]" value="%d" />', $item['id']);
    }

    /**
     * Coluna de ações personalizada.
     */
    protected function column_actions($item) {
        return $item['actions'];
    }
}
