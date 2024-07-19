<?php
/*
Plugin Name: Knowie WP Chatbot
Plugin URI: https://github.com/bandid
Description: This plugin integrates with the OpenAI API to provide a chatbot using a shortcode.
Version: 1.0
Author: Daniel Bandi
Author URI: https://github.com/bandid
Text Domain: knowie-wp-chatbot
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class knowieWPChatbot {
    private $api_key_option = 'knowie_wp_api_key';
    private $model_option = 'knowie_wp_model';
    private $temperature_option = 'knowie_wp_temperature';
    private $max_tokens_option = 'knowie_wp_max_tokens';
    private $system_prompt_option = 'knowie_wp_system_prompt';
    private $default_message_option = 'knowie_wp_default_message';
    private $context_awareness_option = 'knowie_wp_context_awareness';
    private $chat_icon_option = 'knowie_wp_chat_icon';

    public function __construct() {
        add_action('admin_menu', array($this, 'create_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('wp_ajax_knowie_chatbot', array($this, 'generate_chat_response'));
        add_action('wp_ajax_nopriv_knowie_chatbot', array($this, 'generate_chat_response'));
        add_action('admin_init', array($this, 'register_settings'));
        add_shortcode('knowie_wp_chatbot', array($this, 'render_chatbot'));
    }

    public function create_menu() {
        add_menu_page(
            'Knowie WP Chatbot',
            'Knowie WP Chatbot',
            'manage_options',
            'knowie-wp-chatbot',
            array($this, 'settings_page'),
            'dashicons-format-chat'
        );
    }

    public function settings_page() {
        ?>
        <div class="wrap knowie-wp-chatbot-admin">
            <h1><img src="<?php echo plugins_url('/assets/images/knowie-logo.png', __FILE__); ?>" alt="Knowie Logo" class="knowie-logo">
			Knowie WP Chatbot
			</h1>
            <form method="post" action="options.php">
                <?php settings_fields('knowie_wp_chatbot_settings_group'); ?>
                <?php do_settings_sections('knowie-wp-chatbot-settings'); ?>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function enqueue_admin_scripts($hook) {
        if ($hook != 'toplevel_page_knowie-wp-chatbot') {
            return;
        }

        wp_enqueue_style('knowie-wp-chatbot-admin-style', plugins_url('/assets/css/admin-style.css', __FILE__));
        
        wp_enqueue_media();
        wp_enqueue_script('knowie-wp-chatbot-admin-script', plugins_url('/assets/js/admin-script.js', __FILE__), array('jquery'), '1.0', true);
    }

    public function enqueue_frontend_scripts() {
        wp_enqueue_style('knowie-wp-chatbot-style', plugins_url('/assets/css/chatbot-style.css', __FILE__));
		wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css');
        wp_enqueue_script('knowie-wp-chatbot-script', plugins_url('/assets/js/chatbot-script.js', __FILE__), array('jquery'), '1.0', true);
        wp_localize_script('knowie-wp-chatbot-script', 'knowieWPChatbot', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('knowie_wp_chatbot_nonce')
        ));
    }

    public function generate_chat_response() {
        check_ajax_referer('knowie_wp_chatbot_nonce', 'nonce');

        $user_message = sanitize_text_field($_POST['message']);
        $api_key = get_option($this->api_key_option);
        $model = get_option($this->model_option, 'gpt-3.5-turbo');
        $temperature = floatval(get_option($this->temperature_option, 0.7));
        $max_tokens = intval(get_option($this->max_tokens_option, 1000));
        $system_prompt = get_option($this->system_prompt_option, 'You are a helpful assistant.');
        $context_awareness = get_option($this->context_awareness_option);

        $context = '';
        if ($context_awareness) {
            $context = $this->get_context_from_site();
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array(
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => $context . "\nUser: " . $user_message]
            ],
            'max_tokens'  => $max_tokens,
            'temperature' => $temperature
        )));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json'
        ));

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            wp_send_json_error('cURL error: ' . curl_error($ch));
        } else {
            $data = json_decode($response, true);

            if (isset($data['choices'][0]['message']['content'])) {
                wp_send_json_success($data['choices'][0]['message']['content']);
            } else {
                wp_send_json_error('Invalid response from OpenAI API. Response data: ' . print_r($data, true));
            }
        }

        curl_close($ch);
    }

    private function get_context_from_site() {
        $response = wp_remote_get(get_site_url() . '/wp-json/wp/v2/posts');
        if (is_wp_error($response)) {
            return '';
        }
        $posts = json_decode(wp_remote_retrieve_body($response), true);
        $context = "Recent posts:\n";
        foreach ($posts as $post) {
            $context .= $post['title']['rendered'] . ": " . wp_strip_all_tags($post['excerpt']['rendered']) . "\n";
        }
        return $context;
    }

    public function register_settings() {
        register_setting('knowie_wp_chatbot_settings_group', $this->api_key_option);
        register_setting('knowie_wp_chatbot_settings_group', $this->model_option);
        register_setting('knowie_wp_chatbot_settings_group', $this->temperature_option);
        register_setting('knowie_wp_chatbot_settings_group', $this->max_tokens_option);
        register_setting('knowie_wp_chatbot_settings_group', $this->system_prompt_option);
        register_setting('knowie_wp_chatbot_settings_group', $this->default_message_option);
        register_setting('knowie_wp_chatbot_settings_group', $this->context_awareness_option);
        register_setting('knowie_wp_chatbot_settings_group', $this->chat_icon_option);

        add_settings_section('knowie_wp_chatbot_settings_section', 'OpenAI Settings', null, 'knowie-wp-chatbot-settings');

        add_settings_field($this->api_key_option, 'API Key', array($this, 'api_key_field_html'), 'knowie-wp-chatbot-settings', 'knowie_wp_chatbot_settings_section');
        add_settings_field($this->model_option, 'Model', array($this, 'model_field_html'), 'knowie-wp-chatbot-settings', 'knowie_wp_chatbot_settings_section');
        add_settings_field($this->temperature_option, 'Temperature', array($this, 'temperature_field_html'), 'knowie-wp-chatbot-settings', 'knowie_wp_chatbot_settings_section');
        add_settings_field($this->max_tokens_option, 'Max Tokens', array($this, 'max_tokens_field_html'), 'knowie-wp-chatbot-settings', 'knowie_wp_chatbot_settings_section');
        add_settings_field($this->system_prompt_option, 'System Prompt', array($this, 'system_prompt_field_html'), 'knowie-wp-chatbot-settings', 'knowie_wp_chatbot_settings_section');
        add_settings_field($this->default_message_option, 'Default Message', array($this, 'default_message_field_html'), 'knowie-wp-chatbot-settings', 'knowie_wp_chatbot_settings_section');
        add_settings_field($this->context_awareness_option, 'Context Awareness', array($this, 'context_awareness_field_html'), 'knowie-wp-chatbot-settings', 'knowie_wp_chatbot_settings_section');
        add_settings_field($this->chat_icon_option, 'Chat Icon', array($this, 'chat_icon_field_html'), 'knowie-wp-chatbot-settings', 'knowie_wp_chatbot_settings_section');
    }

    public function api_key_field_html() {
        $value = get_option($this->api_key_option);
        echo '<input type="text" id="' . $this->api_key_option . '" name="' . $this->api_key_option . '" value="' . esc_attr($value) . '" style="width: 100%;" />';
        echo '<p>Enter your OpenAI API key.</p>';
    }

    public function model_field_html() {
        $value = get_option($this->model_option, 'gpt-3.5-turbo');
        $options = array('gpt-3.5-turbo', 'gpt-4', 'gpt-4-turbo', 'gpt-4o', 'gpt-4o-mini');
        echo '<select id="' . $this->model_option . '" name="' . $this->model_option . '">';
        foreach ($options as $option) {
            $selected = ($value == $option) ? 'selected' : '';
            echo '<option value="' . esc_attr($option) . '" ' . $selected . '>' . esc_html($option) . '</option>';
        }
        echo '</select>';
        echo '<p>Select the default model to use.</p>';
    }

    public function temperature_field_html() {
        $value = get_option($this->temperature_option, 0.7);
        echo '<input type="number" id="' . $this->temperature_option . '" name="' . $this->temperature_option . '" value="' . esc_attr($value) . '" step="0.1" min="0" max="2" style="width: 100%;" />';
        echo '<p>What sampling temperature to use, between 0 and 2. Higher values like 0.8 will make the output more random, while lower values like 0.2 will make it more focused and deterministic.</p>';
    }

    public function max_tokens_field_html() {
        $value = get_option($this->max_tokens_option, 1000);
        echo '<input type="number" id="' . $this->max_tokens_option . '" name="' . $this->max_tokens_option . '" value="' . esc_attr($value) . '" min="1" style="width: 100%;" />';
        echo '<p>The maximum number of tokens to generate in the chat completion.</p>';
    }

    public function system_prompt_field_html() {
        $value = get_option($this->system_prompt_option, 'You are a helpful assistant.');
        echo '<textarea id="' . $this->system_prompt_option . '" name="' . $this->system_prompt_option . '" rows="5" style="width: 100%;">' . esc_textarea($value) . '</textarea>';
    }

    public function default_message_field_html() {
        $value = get_option($this->default_message_option, 'Hello, how may I assist you today?');
        echo '<input type="text" id="' . $this->default_message_option . '" name="' . $this->default_message_option . '" value="' . esc_attr($value) . '" style="width: 100%;" />';
        echo '<p>Enter the default message the chatbot will display.</p>';
    }

    public function context_awareness_field_html() {
        $value = get_option($this->context_awareness_option);
        echo '<input type="checkbox" id="' . $this->context_awareness_option . '" name="' . $this->context_awareness_option . '" value="1" ' . checked(1, $value, false) . ' />';
        echo '<p>Enable context-awareness: When enabled, the chatbot will be aware of the recent posts and content on your site.</p>';
    }

    public function chat_icon_field_html() {
        $icon_url = get_option($this->chat_icon_option, plugins_url('/assets/images/chat-icon.png', __FILE__));
        ?>
        <div>
            <img src="<?php echo esc_url($icon_url); ?>" alt="Chat Icon" style="max-width: 100px; height: auto;">
            <input type="hidden" id="<?php echo $this->chat_icon_option; ?>" name="<?php echo $this->chat_icon_option; ?>" value="<?php echo esc_attr($icon_url); ?>">
            <button type="button" class="button" id="upload_chat_icon_button">Upload New Icon</button>
            <p class="description">Upload a new chat icon or use the default one.</p>
        </div>
        <?php
    }

    public function render_chatbot() {
        $default_message = get_option($this->default_message_option, 'Hello, how may I assist you today?');
        $chat_icon = get_option($this->chat_icon_option, plugins_url('/assets/images/chat-icon.png', __FILE__));
		ob_start();
        ?>
        <div class="chatbot-container knowie-wp-chatbot">
            <div class="custom-chatbot__image" onclick="knowieToggleChat()">
                <img src="<?php echo esc_url($chat_icon); ?>" alt="Chatbot Icon" class="chat-icon">
            </div>
            <div class="custom-chatbot">
                <div class="chat">
                    <div class="chat__header">
                        <div>
                            <div class="chat__title">Welcome</div>
                            
                        </div>
                        <div>
                            <div class="chat__close-icon" onclick="knowieToggleChat()">
                                <i class="fa-solid fa-xmark"></i>
                            </div>
                        </div>
                    </div>

                    <div class="chat__messages">
                        <div class="message bot"><?php echo esc_html($default_message); ?></div>
                    </div>

                    <div class="chat__input-area">
                        <form id="knowie-messageForm" onsubmit="knowieOnFormSubmit(event)">
                            <div>
                                <div class="input">
                                    <div>
                                        <input type="text" id="knowie-message" name="message" placeholder="Type your message" autocomplete="off" required>
                                    </div>
                                </div>
                                <div>
                                    <button type="submit" id="knowie-submit-btn"><i class="fa-solid fa-paper-plane"></i></button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
		return ob_get_clean();
    }
}

new knowieWPChatbot();

register_uninstall_hook(__FILE__, 'knowie_wp_chatbot_uninstall');
function knowie_wp_chatbot_uninstall() {
    $options = array(
        'knowie_wp_api_key',
        'knowie_wp_model',
        'knowie_wp_temperature',
        'knowie_wp_max_tokens',
        'knowie_wp_system_prompt',
        'knowie_wp_default_message',
        'knowie_wp_context_awareness',
        'knowie_wp_chat_icon',
    );

    foreach ($options as $option) {
        delete_option($option);
    }
}
?>
