<?php
declare(strict_types=1);

/**
 * Registers all action and filter hooks for the plugin.
 *
 * @package    Wcac_Customer_AI_Chatbot
 * @subpackage Wcac_Customer_AI_Chatbot/includes
 */
class Wcac_Loader {

    protected array $actions;
    protected array $filters;
    protected Wcac_Indexer $indexer;

    public function __construct() {
        $this->actions = [];
        $this->filters = [];
        $this->indexer = new Wcac_Indexer(); // Instantiate the indexer
    }

    public function add_action( string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1 ): void {
        $this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
    }

    public function add_filter( string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1 ): void {
        $this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
    }

    private function add( array $hooks, string $hook, object $component, string $callback, int $priority, int $accepted_args ): array {
        $hooks[] = [
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        ];
        return $hooks;
    }

    public function run(): void {
        foreach ( $this->filters as $hook ) {
            add_filter( $hook['hook'], [ $hook['component'], $hook['callback'] ], $hook['priority'], $hook['accepted_args'] );
        }

        foreach ( $this->actions as $hook ) {
            add_action( $hook['hook'], [ $hook['component'], $hook['callback'] ], $hook['priority'], $hook['accepted_args'] );
        }

        // Add specific hook for product saving
        add_action( 'save_post_product', [ $this->indexer, 'update_single_product' ], 10, 1 );
        // We might need to handle different parameters later if save_post is used directly
        // add_action( 'save_post', [$this->indexer, 'handle_save_post'], 10, 3 );

        // REMOVED: AJAX handler now handled in main plugin file.
        // add_action( 'wp_ajax_wcac_build_index', [ $this, 'handle_build_index_ajax' ] );
    }

    // REMOVED: AJAX handler method is now in main plugin file.
	// /**
    //  * Handles the AJAX request to manually build the index.
    //  *
    //  * @since 0.1.0
    //  */
    // public function handle_build_index_ajax(): void {
    //     // Security check
    //     if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wcac_reindex_nonce' ) ) {
    //         wp_send_json_error(['message' => esc_html__( 'Nonce verification failed.', 'wp-customer-ai-chatbot' )]);
    //         return;
    //     }

    //     // Permission check
    //     if ( ! current_user_can( 'manage_options' ) ) {
    //         wp_send_json_error(['message' => esc_html__( 'You do not have permission to perform this action.', 'wp-customer-ai-chatbot' )]);
    //         return;
    //     }

    //     // Perform indexing
    //     $result = $this->indexer->build_index();

    //     if ( $result['error'] !== null ) {
    //         wp_send_json_error(['message' => $result['error']]);
    //     } else {
    //         wp_send_json_success(['count' => $result['count']]);
    //     }
    // }

	// Method to handle save_post if needed (checks post type)
	// public function handle_save_post( int $post_id, WP_Post $post, bool $update ): void {
	// 	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
	// 		return;
	// 	}
	// 	if ( $post->post_type === 'product' ) {
	// 		$this->indexer->update_single_product( $post_id );
	// 	}
	// }
} 