<?php
/**
 * Plugin Name: Gemini A-Frame Generator
 * Description: A self-contained plugin to generate and iterate on A-Frame.js scenes using the Gemini API.
 * Version: 3.2.4
 * Author: Your Name
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// === 1. SETTINGS PAGE (No changes here) ===
function gag_add_admin_menu() {
    add_options_page('Gemini A-Frame Generator', 'Gemini A-Frame', 'manage_options', 'gemini_aframe_generator', 'gag_options_page_html');
}
add_action('admin_menu', 'gag_add_admin_menu');

function gag_settings_init() {
    register_setting('gag_plugin_page', 'gag_settings');
}
add_action('admin_init', 'gag_settings_init');

function gag_options_page_html() {
    if (!current_user_can('manage_options')) { return; }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <p>Enter your Google AI API key below. You can find your key at the <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a>.</p>
        <form action="options.php" method="post" style="margin-top: 20px;">
            <?php
            settings_fields('gag_plugin_page');
            $options = get_option('gag_settings');
            $api_key = isset($options['api_key']) ? esc_attr($options['api_key']) : '';
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Gemini API Key</th>
                    <td>
                        <input type="password" name="gag_settings[api_key]" value="<?php echo $api_key; ?>" size="50" />
                    </td>
                </tr>
            </table>
            <?php submit_button('Save Settings'); ?>
        </form>
    </div>
    <?php
}

// === 2. MAIN SHORTCODE UI ===
function gag_shortcode_ui() {
    ob_start();
    ?>
    <script src="https://aframe.io/releases/1.5.0/aframe.min.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/c-frame/aframe-extras@7.2.0/dist/aframe-extras.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/aframe-physics-system@4.2.2/dist/aframe-physics-system.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/aframe-super-hands-component@3.0.4/dist/aframe-super-hands-component.min.js"></script>

    <style>
    /* Plugin UI Styles */
    #gemini-aframe-container { max-width: 700px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background: #f9f9f9; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; box-sizing: border-box; } #gemini-aframe-container *, #gemini-aframe-container *:before, #gemini-aframe-container *:after { box-sizing: inherit; } #gemini-aframe-container h3 { margin-top: 25px; text-align: center; padding-bottom: 10px; border-bottom: 1px solid #eee; margin-bottom: 20px; } #prompt-enhancer-container { margin-bottom: 30px; } #prompt-enhancer-container fieldset { border: 1px solid #0073aa; border-radius: 6px; padding: 15px 20px 20px 20px; background-color: #f0f6fa; } #prompt-enhancer-container legend { font-weight: bold; font-size: 1.1em; padding: 0 10px; color: #0073aa; margin-left: -10px; } #prompt-enhancer-container p { font-size: 0.9em; color: #555; margin-top: 0; line-height: 1.5; } #gemini-aframe-container textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; margin-bottom: 10px; font-size: 16px; resize: vertical; } #prompt-enhancer-container button { width: 100%; padding: 10px; background-color: #2a9d8f; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; } #gemini-prompt-form button[type="submit"] { width: 100%; padding: 12px; background-color: #0073aa; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: bold; } #gemini-aframe-container button:disabled { background-color: #999 !important; cursor: not-allowed !important; } .spinner { border: 6px solid #f3f3f3; border-top: 6px solid #3498db; border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; margin: 0 auto 15px auto; } @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } } #scene-controls { display: none; margin-top: 20px; padding: 15px; border: 1px solid #ccc; border-radius: 6px; background-color: #f5f5f5; } #iteration-form h4 { margin-top: 0; } #update-scene-btn { width: 100%; padding: 10px; background-color: #f0ad4e; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; } #view-scene-btn { padding: 8px 12px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; } #iteration-buttons { display: flex; gap: 10px; margin-top: -5px; } #enhance-iteration-btn { width: 100%; padding: 10px; background-color: #2a9d8f; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; } #iteration-buttons button { flex: 1; }
    .scene-actions { display: flex; gap: 10px; margin: 15px 0 5px 0; } .scene-actions button { flex: 1; } #submit-post-btn { padding: 8px 12px; background-color: #337ab7; color: white; border: none; border-radius: 4px; cursor: pointer; } #view-code-btn { padding: 8px 12px; background-color: #5bc0de; color: white; border: none; border-radius: 4px; cursor: pointer; }
    #post-success-message { display: none; padding: 10px; margin-top: 15px; background-color: #dff0d8; border: 1px solid #d6e9c6; color: #3c763d; border-radius: 4px; }

    /* Modal Styles */
    .gag-modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.85); z-index: 10000; }
    #gag-modal-content { position: relative; width: 80%; height: 80%; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #111; border: 2px solid #555; }
    .gag-modal-close-btn { position: absolute; top: -15px; right: -15px; width: 32px; height: 32px; background: #fff; color: #000; border-radius: 50%; border: 2px solid #000; font-size: 20px; text-align: center; line-height: 28px; cursor: pointer; font-weight: bold; z-index: 20; }
    #gemini-aframe-results { width: 100%; height: 100%; }
    .gag-loader-modal { display: flex; justify-content: center; align-items: center; color: #fff; width: 100%; height: 100%; }
    #gag-fullscreen-btn { position: absolute; bottom: 15px; right: 15px; background: rgba(0, 0, 0, 0.5); color: white; border: 1px solid rgba(255, 255, 255, 0.8); border-radius: 5px; padding: 10px 15px; cursor: pointer; z-index: 20; font-family: -apple-system, BlinkMacSystemFont, sans-serif; font-size: 14px; }
    
    /* Code Modal Styles */
    #gag-code-modal-content { position: relative; width: 80%; height: 80%; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #2d2d2d; border: 2px solid #555; display: flex; flex-direction: column; }
    #code-display-header { padding: 10px 15px; background: #1e1e1e; border-bottom: 1px solid #444; }
    #copy-code-btn { background: #4CAF50; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; }
    #code-display-area { flex-grow: 1; overflow: auto; margin: 0; background: #1e1e1e; color: #d4d4d4; font-family: Consolas, Monaco, 'Andale Mono', 'Ubuntu Mono', monospace; font-size: 14px; white-space: pre; padding: 15px; }
    </style>
    
    <div id="gemini-aframe-container">
        <form id="gemini-prompt-form">
            <div id="prompt-enhancer-container">
                <fieldset><legend>Step 1: Get an Idea</legend><p>Start with a simple idea. We'll use AI to expand it into a detailed, A-Frame-aware prompt.</p><textarea id="basic-prompt" rows="2" placeholder="e.g., a low-poly castle"></textarea><button type="button" id="enhance-prompt-btn">Enhance Prompt</button></fieldset>
            </div>
            <h3>Step 2: Generate or Modify Scene</h3>
            <textarea id="gemini-prompt" rows="5" placeholder="Your enhanced prompt will appear here... or write your own."></textarea>
            <button type="submit">Generate Scene</button>
        </form>

        <div id="scene-controls">
            <div class="scene-actions">
                <button id="view-scene-btn">View Scene</button>
                <button id="view-code-btn">View Code</button>
                <button id="submit-post-btn">Save as Draft</button>
            </div>
             <div id="post-success-message"></div>
            <form id="iteration-form">
                <h4>Step 3: Iterate on this Scene</h4>
                <textarea id="iteration-prompt" rows="3" placeholder="Describe your change... e.g., 'add floating rocks' or 'make the ground lava'"></textarea>
                <div id="iteration-buttons">
                    <button type="button" id="enhance-iteration-btn">Enhance Request</button>
                    <button type="button" id="update-scene-btn">Update Scene</button>
                </div>
            </form>
        </div>
    </div>

    <div id="gag-modal-overlay" class="gag-modal-overlay">
        <div id="gag-modal-content">
            <button class="gag-modal-close-btn">&times;</button>
            <button id="gag-fullscreen-btn">Fullscreen</button>
            <div id="gemini-aframe-results">
                 </div>
        </div>
    </div>

    <div id="gag-code-modal-overlay" class="gag-modal-overlay">
        <div id="gag-code-modal-content">
            <button class="gag-modal-close-btn">&times;</button>
            <div id="code-display-header">
                <button id="copy-code-btn">Copy Code</button>
            </div>
            <pre id="code-display-area"><code></code></pre>
        </div>
    </div>

    <script>
    const gag_ajax_object = { ajax_url: "<?php echo admin_url('admin-ajax.php'); ?>", nonce: "<?php echo wp_create_nonce('gag_gemini_nonce'); ?>"}; 
    let currentSceneCode = '';
    let justGenerated = false;
    
    jQuery(document).ready(function($) {
        const modalContent = document.getElementById('gag-modal-content');
        const fullscreenBtn = document.getElementById('gag-fullscreen-btn');

        function cleanAICode(rawCode) { if (!rawCode) return ''; return rawCode.replace(/^```html\s*/, '').replace(/```$/, '').trim(); }
        
        // Helper functions to manage modal state and page scrollability
        function openGagModal() {
            $('html, body').css('overflow', 'hidden'); // Target both html and body for max compatibility
            $('#gag-modal-overlay').show();
        }

        // *** UPDATED CODE IS HERE ***
        function closeGagModal() {
            // A-Frame adds the 'a-body' class which sets overflow: hidden via its stylesheet.
            // We must remove this class AND explicitly reset the inline style to ensure scrolling is restored.
            $('body').removeClass('a-body');
            $('html, body').css('overflow', 'auto');
            $('#gag-modal-overlay').hide();
        }

        // --- Fullscreen Controls ---
        function updateFullscreenButton() { if (document.fullscreenElement === modalContent) { fullscreenBtn.textContent = 'Exit Fullscreen'; } else { fullscreenBtn.textContent = 'Fullscreen'; } }
        fullscreenBtn.addEventListener('click', function() { if (!document.fullscreenElement) { modalContent.requestFullscreen().catch(err => { alert(`Could not enter fullscreen mode: ${err.message}`); }); } else { document.exitFullscreen(); } });
        document.addEventListener('fullscreenchange', updateFullscreenButton);

        // --- Scene Modal Controls ---
        $('#view-scene-btn').on('click', function() { 
            if (currentSceneCode) {
                // Re-inject the scene every time the view button is clicked.
                $('#gemini-aframe-results').html(currentSceneCode);
                openGagModal();
                // A-Frame needs a moment to initialize after being injected
                setTimeout(() => {
                    const sceneEl = document.querySelector('#gemini-aframe-results a-scene');
                    if (sceneEl) {
                        sceneEl.play();
                    }
                }, 100);
            } else {
                alert('No scene to view. Please generate one first.');
            }
        });
        
        $('#gag-modal-overlay .gag-modal-close-btn').on('click', function() { 
            // Destroy the scene on close to free up all resources and event listeners.
            const sceneEl = document.querySelector('#gemini-aframe-results a-scene');
            if (sceneEl) {
                sceneEl.pause(); // Pause first is good practice
            }
            $('#gemini-aframe-results').html(''); // Empty the container to destroy the scene instance
            closeGagModal();

            if (justGenerated) {
                $('html, body').animate({
                    scrollTop: $("#iteration-form").offset().top - 50
                }, 800);
                justGenerated = false;
            }
        });

        // --- Code Modal Controls ---
        $('#view-code-btn').on('click', function() {
            if (currentSceneCode) {
                const escapedCode = $('<div/>').text(currentSceneCode).html();
                $('#code-display-area').html('<code>' + escapedCode + '</code>');
                $('html, body').css('overflow', 'hidden'); // Lock scroll for code modal too
                $('#gag-code-modal-overlay').show();
            } else { alert('Please generate a scene first.'); }
        });
        $('#gag-code-modal-overlay .gag-modal-close-btn').on('click', function() { 
            $('html, body').css('overflow', ''); // Unlock on close
            $('#gag-code-modal-overlay').hide(); 
        });

        $('#copy-code-btn').on('click', function() {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(currentSceneCode).then(() => {
                    const btn = $(this); const originalText = btn.text(); btn.text('Copied!');
                    setTimeout(() => { btn.text(originalText); }, 2000);
                }).catch(err => { alert('Failed to copy code.'); });
            }
        });

        // --- Save as Post ---
        $('#submit-post-btn').on('click', function() {
            if (!currentSceneCode) { alert('Please generate a scene first.'); return; }
            var btn = $(this); btn.prop('disabled', true).text('Saving...'); $('#post-success-message').hide();
            $.ajax({
                url: gag_ajax_object.ajax_url, type: 'POST', data: { action: 'gag_save_aframe_as_post', nonce: gag_ajax_object.nonce, scene_code: currentSceneCode },
                success: function(response) {
                    if (response.success) { $('#post-success-message').html('Success! <a href="' + response.data.edit_link + '" target="_blank">Edit your new draft post.</a>').show(); } 
                    else { alert('Error saving post: ' + response.data.message); }
                },
                error: function() { alert('An unknown error occurred while saving the post.'); },
                complete: function() { btn.prop('disabled', false).text('Save as Draft'); }
            });
        });

        // --- Prompt Enhancers and Scene Generation/Iteration ---
        $('#enhance-prompt-btn').on('click', function(e) {
            e.preventDefault();
            var basicPrompt = $('#basic-prompt').val(); if (basicPrompt.trim() === '') { alert('Please enter a basic idea.'); return; }
            var btn = $(this); btn.prop('disabled', true).text('Enhancing...'); $('#gemini-prompt').val('AI is thinking...');
            $.ajax({
                url: gag_ajax_object.ajax_url, type: 'POST', data: { action: 'gag_enhance_prompt', nonce: gag_ajax_object.nonce, prompt: basicPrompt },
                success: function(response) {
                    if (response.success) { $('#gemini-prompt').val(response.data.enhanced_prompt); } 
                    else { alert('Error: ' + response.data.message); $('#gemini-prompt').val(''); }
                },
                error: function() { alert('An unknown error occurred.'); $('#gemini-prompt').val(''); },
                complete: function() { btn.prop('disabled', false).text('Enhance Prompt'); }
            });
        });

        $('#gemini-prompt-form').on('submit', function(e) {
            e.preventDefault();
            var promptText = $('#gemini-prompt').val(); if (promptText.trim() === '') { alert('Please enter a scene description.'); return; }
            var btn = $(this).find('button[type="submit"]'); btn.prop('disabled', true).text('Generating...');
            openGagModal(); 
            $('#gemini-aframe-results').html('<div class="gag-loader-modal"><div class="spinner"></div></div>'); // Show loader
            $('#post-success-message').hide();
            $.ajax({
                url: gag_ajax_object.ajax_url, type: 'POST', data: { action: 'gag_get_aframe_scene', nonce: gag_ajax_object.nonce, prompt: promptText },
                success: function(response) {
                    if (response.success) {
                        currentSceneCode = cleanAICode(response.data.aframe_code);
                        $('#gemini-aframe-results').html(currentSceneCode);
                        $('#scene-controls').show();
                        justGenerated = true;
                    } else {
                        closeGagModal(); 
                        $('#gemini-aframe-results').html(''); // Clear on error
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function() { 
                    closeGagModal(); 
                    $('#gemini-aframe-results').html(''); // Clear on error
                    alert('An unknown error occurred.'); 
                },
                complete: function() { btn.prop('disabled', false).text('Generate Scene'); }
            });
        });

        $('#enhance-iteration-btn').on('click', function(e) {
            e.preventDefault();
            var iterationPrompt = $('#iteration-prompt').val(); if (iterationPrompt.trim() === '') { alert('Please describe the change you want to make.'); return; }
            if (currentSceneCode === '') { alert('Please generate a base scene first.'); return; }
            var btn = $(this); btn.prop('disabled', true).text('Enhancing...'); var originalText = $('#iteration-prompt').val(); $('#iteration-prompt').val('AI is analyzing...');
            $.ajax({
                url: gag_ajax_object.ajax_url, type: 'POST', data: { action: 'gag_enhance_iteration_prompt', nonce: gag_ajax_object.nonce, prompt: iterationPrompt, current_scene: currentSceneCode },
                success: function(response) {
                    if (response.success) { $('#iteration-prompt').val(response.data.enhanced_prompt); } 
                    else { alert('Error enhancing prompt: ' + response.data.message); $('#iteration-prompt').val(originalText); }
                },
                error: function() { alert('An unknown error occurred during enhancement.'); $('#iteration-prompt').val(originalText); },
                complete: function() { btn.prop('disabled', false).text('Enhance Request'); }
            });
        });

        $('#update-scene-btn').on('click', function(e) {
            e.preventDefault();
            var iterationPrompt = $('#iteration-prompt').val(); if (iterationPrompt.trim() === '') { alert('Please enter a change to make.'); return; }
            if (currentSceneCode === '') { alert('Please generate a base scene first.'); return; }
            var btn = $(this); btn.prop('disabled', true).text('Updating...');
            openGagModal(); 
            $('#gemini-aframe-results').html('<div class="gag-loader-modal"><div class="spinner"></div></div>'); // Show loader
            $('#post-success-message').hide();
            $.ajax({
                url: gag_ajax_object.ajax_url, type: 'POST', data: { action: 'gag_iterate_scene', nonce: gag_ajax_object.nonce, current_scene: currentSceneCode, next_prompt: iterationPrompt },
                success: function(response) {
                    if (response.success) {
                        currentSceneCode = cleanAICode(response.data.aframe_code);
                        $('#gemini-aframe-results').html(currentSceneCode);
                        $('#iteration-prompt').val('');
                        justGenerated = true;
                    } else {
                        alert('Update failed: ' + response.data.message);
                        $('#gemini-aframe-results').html(currentSceneCode); // Revert to old scene on fail
                    }
                },
                error: function() {
                    alert('An unknown error occurred during the update.'); 
                    $('#gemini-aframe-results').html(currentSceneCode); // Revert on error
                },
                complete: function() { btn.prop('disabled', false).text('Update Scene'); }
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('gemini_aframe_ui', 'gag_shortcode_ui');


// === 3. SHORTCODE FOR DISPLAYING SAVED SCENES (No changes) ===
function gag_display_scene_shortcode($atts) {
    $post_id = get_the_ID();
    if (!$post_id) { return ''; }
    $scene_code = get_post_meta($post_id, '_gag_aframe_code', true);
    if (empty($scene_code)) { return ''; }
    ob_start();
    ?>
    <script src="https://aframe.io/releases/1.5.0/aframe.min.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/c-frame/aframe-extras@7.2.0/dist/aframe-extras.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/aframe-physics-system@4.2.2/dist/aframe-physics-system.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/aframe-super-hands-component@3.0.4/dist/aframe-super-hands-component.min.js"></script>
    <div style="width: 100%; height: 600px; margin: 20px 0;">
        <?php echo $scene_code; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gag_display_scene', 'gag_display_scene_shortcode');


// === 4. AJAX HANDLERS (No changes) ===
function gag_handle_save_aframe_as_post() {
    check_ajax_referer('gag_gemini_nonce', 'nonce');
    if (!current_user_can('publish_posts')) { wp_send_json_error(['message' => 'You do not have permission to create posts.']); }
    $scene_code = isset($_POST['scene_code']) ? stripslashes_deep($_POST['scene_code']) : '';
    if (empty($scene_code)) { wp_send_json_error(['message' => 'Scene code is empty.']); }
    $post_data = [
        'post_title'   => 'A-Frame Scene - ' . current_time('mysql'),
        'post_content' => '[gag_display_scene]',
        'post_status'  => 'draft',
        'post_author'  => get_current_user_id(),
    ];
    $post_id = wp_insert_post($post_data, true);
    if (is_wp_error($post_id)) { wp_send_json_error(['message' => $post_id->get_error_message()]); }
    update_post_meta($post_id, '_gag_aframe_code', $scene_code);
    wp_send_json_success(['edit_link' => get_edit_post_link($post_id, 'raw')]);
}
add_action('wp_ajax_gag_save_aframe_as_post', 'gag_handle_save_aframe_as_post');

function gag_get_api_key() {
    $options = get_option('gag_settings');
    $api_key = isset($options['api_key']) ? $options['api_key'] : '';
    if (empty($api_key)) {
        wp_send_json_error(['message' => 'API Key is not configured in settings.']);
        return null;
    }
    return $api_key;
}

function gag_call_gemini_api($api_key, $payload) {
    $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro-latest:generateContent?key=' . $api_key;
    $response = wp_remote_post($api_url, [
        'method' => 'POST', 'timeout' => 60,
        'headers' => ['Content-Type' => 'application/json', 'User-Agent' => 'WordPress/'. get_bloginfo('version') .'; ' . get_bloginfo('url')],
        'body' => json_encode($payload)
    ]);
    if (is_wp_error($response)) { wp_send_json_error(['message' => 'WordPress could not connect to the API. Error: ' . $response->get_error_message()]); }
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    if (isset($data['error'])) { wp_send_json_error(['message' => 'Google API Error: ' . ($data['error']['message'] ?? 'Unknown error.')]); }
    $text_response = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if (empty($text_response)) { wp_send_json_error(['message' => 'The AI returned an empty response. This may be due to a safety filter.']); }
    return $text_response;
}

function gag_handle_gemini_prompt() {
    check_ajax_referer('gag_gemini_nonce', 'nonce');
    $api_key = gag_get_api_key();
    
    $user_prompt = isset($_POST['prompt']) ? sanitize_textarea_field($_POST['prompt']) : '';
    if (empty($user_prompt)) { wp_send_json_error(['message' => 'Prompt cannot be empty.']); }

    $system_prompt = "You are an A-Frame scene content generator. Your task is to generate ONLY the A-Frame entities that make up a scene's content (objects, ground, etc.) based on the user's prompt.
RULES:
- Your entire output MUST be ONLY A-Frame entities like `<a-box>`, `<a-sphere>`, `<a-plane>`, etc.
- **DO NOT include `<a-scene>`, `<a-sky>`, lighting entities, or a camera/player rig.** These will be added automatically.
- Any ground surface (like `<a-plane>`) MUST be a `static-body` and have `class=\"collidable\"` to allow teleportation.
- Interactive objects MUST have `grabbable`, a `dynamic-body` component, and `class=\"interactive\"`.";
    
    $payload = [
        'contents' => [['parts' => [['text' => $system_prompt], ['text' => "User Prompt: " . $user_prompt]]]],
        'generationConfig' => ['temperature' => 0.7, 'maxOutputTokens' => 4096]
    ];

    $generated_entities = gag_call_gemini_api($api_key, $payload);

    $full_scene_code = <<<HTML
<a-scene 
    physics="driver: local" 
    vr-mode-ui="enabled: true" 
    background="color: #ECECEC" 
    renderer="colorManagement: true">

    <a-entity light="type: ambient; color: #888; intensity: 1.2"></a-entity>
    <a-entity light="type: directional; color: #FFF; intensity: 1" position="-1 2 2"></a-entity>

    <a-entity id="player"
        movement-controls="controls: keyboard, touch, gamepad; speed: 0.2"
        teleport-controls="cameraRig: #player; teleportOrigin: #camera; button: trigger; curveShootingSpeed: 10; collisionEntities: .collidable">
      <a-entity id="camera" camera position="0 1.6 0" look-controls></a-entity>
      <a-entity oculus-touch-controls="hand: left" laser-controls="hand: left" raycaster="objects: .interactive; far: 5;" super-hands></a-entity>
      <a-entity oculus-touch-controls="hand: right" laser-controls="hand: right" raycaster="objects: .interactive; far: 5;" super-hands></a-entity>
    </a-entity>

    {$generated_entities}

</a-scene>
HTML;

    wp_send_json_success(['aframe_code' => $full_scene_code]);
}
add_action('wp_ajax_gag_get_aframe_scene', 'gag_handle_gemini_prompt');

function gag_handle_iterate_scene() {
    check_ajax_referer('gag_gemini_nonce', 'nonce');
    $api_key = gag_get_api_key();
    
    $current_scene = isset($_POST['current_scene']) ? stripslashes_deep($_POST['current_scene']) : '';
    $next_prompt = isset($_POST['next_prompt']) ? sanitize_textarea_field($_POST['next_prompt']) : '';
    if (empty($current_scene) || empty($next_prompt)) { wp_send_json_error(['message' => 'Missing current scene or next prompt.']); }

    $system_prompt = "You are an expert A-Frame code editor. You will be given a block of existing A-Frame code and a user prompt describing a change. Your task is to modify the existing code to incorporate the user's request.
RULES:
- Preserve existing elements and properties unless the prompt explicitly asks to change or remove them.
- **Ensure proper lighting (ambient and directional) is preserved.**
- Ensure the player rig (entity with `movement-controls`) and its camera/controller children are preserved EXACTLY as they were.
- Your output must be the complete, modified, valid A-Frame scene, starting with `<a-scene>` and ending with `</a-scene>`.
- Do NOT include any explanations, comments, or markdown.";

    $full_prompt_text = "Here is the current A-Frame code:\n```html\n" . $current_scene . "\n```\n\nHere is the requested change:\n" . $next_prompt;
    
    $payload = [
        'contents' => [['parts' => [['text' => $system_prompt], ['text' => $full_prompt_text]]]],
        'generationConfig' => ['temperature' => 0.5, 'maxOutputTokens' => 8192]
    ];
    
    $aframe_code = gag_call_gemini_api($api_key, $payload);
    wp_send_json_success(['aframe_code' => $aframe_code]);
}
add_action('wp_ajax_gag_iterate_scene', 'gag_handle_iterate_scene');

function gag_handle_enhancer_prompt() {
    check_ajax_referer('gag_gemini_nonce', 'nonce');
    $api_key = gag_get_api_key();
    $basic_prompt = isset($_POST['prompt']) ? sanitize_textarea_field($_POST['prompt']) : '';
    if (empty($basic_prompt)) { wp_send_json_error(['message' => 'Basic prompt cannot be empty.']); }
    $system_prompt = "You are an expert A-Frame prompt engineer. Your task is to take a user's simple idea and expand it into a rich, descriptive prompt.\n\n**Crucially, the final prompt will be fed to another AI whose ONLY job is to write A-Frame.js code for scene content.** Therefore, you MUST use language and concepts that translate directly to A-Frame primitives and entities.\n\nRULES:\n- Emphasize primitives like `<a-box>`, `<a-sphere>`, `<a-plane>`, etc.\n- Suggest including 'several small, grabbable objects (like boxes or spheres) that can be picked up and thrown'. These need `grabbable`, `dynamic-body`, and `class=\"interactive\"`.\n- For 'the ground', suggest 'a large `<a-plane>` for the ground that is a `static-body` and has the `collidable` class'.\n- Describe attributes like `color`, `position`, `rotation`, and `scale`.\n- Respond ONLY with the new, enhanced prompt text. Do not add any conversational text or explanations.";
    $payload = [ 'contents' => [['parts' => [['text' => $system_prompt], ['text' => "User's simple idea: " . $basic_prompt]]]], 'generationConfig' => ['temperature' => 0.8, 'maxOutputTokens' => 512] ];
    $enhanced_prompt = gag_call_gemini_api($api_key, $payload);
    wp_send_json_success(['enhanced_prompt' => trim($enhanced_prompt)]);
}
add_action('wp_ajax_gag_enhance_prompt', 'gag_handle_enhancer_prompt');

function gag_handle_enhancer_iteration_prompt() {
    check_ajax_referer('gag_gemini_nonce', 'nonce');
    $api_key = gag_get_api_key();
    $user_prompt = isset($_POST['prompt']) ? sanitize_textarea_field($_POST['prompt']) : '';
    $current_scene = isset($_POST['current_scene']) ? stripslashes_deep($_POST['current_scene']) : '';
    if (empty($user_prompt) || empty($current_scene)) { wp_send_json_error(['message' => 'Missing prompt or scene context.']); }
    $system_prompt = "You are an expert A-Frame prompt engineer who specializes in scene modification. You will be given the complete HTML for an existing A-Frame scene and a user's simple request for a change. Your task is to rewrite the user's request into a more detailed and descriptive prompt that will be given to another AI whose only job is to edit the code.\n\nRULES:\n- Analyze the provided A-Frame code to understand the context of the scene.\n- Use A-Frame primitives (`<a-box>`, etc.) and attributes (`position`, `color`, `animation`) in your suggested prompt.\n- Be specific. If the user says 'add a car', you should suggest 'add a car made of a few `<a-box>` primitives, with `<a-cylinder>` for wheels, and place it on the ground plane.'\n- If the user asks for new interactive items, remind the model to give them `grabbable`, `dynamic-body`, and `class=\"interactive\"` attributes.\n- Your entire response MUST be ONLY the new, enhanced prompt text. Do not include any conversational text, explanations, or markdown.";
    $full_prompt_text = "Here is the current A-Frame code:\n```html\n" . $current_scene . "\n```\n\nHere is the user's simple request for a change:\n" . $user_prompt;
    $payload = [ 'contents' => [['parts' => [['text' => $system_prompt], ['text' => $full_prompt_text]]]], 'generationConfig' => ['temperature' => 0.8, 'maxOutputTokens' => 1024] ];
    $enhanced_prompt = gag_call_gemini_api($api_key, $payload);
    wp_send_json_success(['enhanced_prompt' => trim($enhanced_prompt)]);
}
add_action('wp_ajax_gag_enhance_iteration_prompt', 'gag_handle_enhancer_iteration_prompt');


// Nopriv versions
add_action('wp_ajax_nopriv_gag_get_aframe_scene', 'gag_handle_gemini_prompt');
add_action('wp_ajax_nopriv_gag_enhance_prompt', 'gag_handle_enhancer_prompt');
add_action('wp_ajax_nopriv_gag_iterate_scene', 'gag_handle_iterate_scene');
add_action('wp_ajax_nopriv_gag_enhance_iteration_prompt', 'gag_handle_enhancer_iteration_prompt');
