<?php
/**
 * Debug Dashboard View
 *
 * @package UTG
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Get upload directory information
$upload_dir = wp_upload_dir();
$wc_logs_dir = $upload_dir['basedir'] . '/wc-logs';
$utg_debug_dir = $upload_dir['basedir'] . '/utg-debug';
$utg_logs_dir = $wc_logs_dir . '/utg-logs';

// Default to first tab
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'logs';
?>

<div class="wrap">
    <h1><?php esc_html_e('URL to Gutenberg - Debug Dashboard', 'url-to-gutenberg'); ?></h1>
    
    <h2 class="nav-tab-wrapper">
        <a href="?page=url-to-gutenberg-debug&tab=logs" class="nav-tab <?php echo $active_tab === 'logs' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Log Files', 'url-to-gutenberg'); ?>
        </a>
        <a href="?page=url-to-gutenberg-debug&tab=extracted" class="nav-tab <?php echo $active_tab === 'extracted' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Extracted Articles', 'url-to-gutenberg'); ?>
        </a>
        <a href="?page=url-to-gutenberg-debug&tab=llm" class="nav-tab <?php echo $active_tab === 'llm' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('LLM Output', 'url-to-gutenberg'); ?>
        </a>
    </h2>
    
    <div class="utg-debug-content">
        <?php if ($active_tab === 'logs'): ?>
            <!-- Log Files Tab -->
            <div class="utg-debug-section">
                <h3><?php esc_html_e('Log Files', 'url-to-gutenberg'); ?></h3>
                <p><?php esc_html_e('View plugin log files to debug issues with URL processing.', 'url-to-gutenberg'); ?></p>
                
                <?php
                // List log files from wc-logs directory
                if (file_exists($wc_logs_dir)) {
                    $log_files = glob($wc_logs_dir . '/*.log');
                    
                    if (!empty($log_files)) {
                        echo '<h4>' . esc_html__('WC Logs Directory', 'url-to-gutenberg') . '</h4>';
                        echo '<table class="widefat" style="margin-bottom: 20px;">';
                        echo '<thead><tr><th>' . esc_html__('Log File', 'url-to-gutenberg') . '</th><th>' . esc_html__('Date', 'url-to-gutenberg') . '</th><th>' . esc_html__('Size', 'url-to-gutenberg') . '</th><th>' . esc_html__('Actions', 'url-to-gutenberg') . '</th></tr></thead>';
                        echo '<tbody>';
                        
                        foreach ($log_files as $log_file) {
                            if (strpos(basename($log_file), 'utg') !== false || strpos(basename($log_file), 'url-to-gutenberg') !== false) {
                                $file_name = basename($log_file);
                                $file_time = filemtime($log_file);
                                $file_size = size_format(filesize($log_file));
                                
                                echo '<tr>';
                                echo '<td>' . esc_html($file_name) . '</td>';
                                echo '<td>' . esc_html(date('Y-m-d H:i:s', $file_time)) . '</td>';
                                echo '<td>' . esc_html($file_size) . '</td>';
                                echo '<td><a href="#" class="utg-view-log" data-file="' . esc_attr($log_file) . '">' . esc_html__('View', 'url-to-gutenberg') . '</a></td>';
                                echo '</tr>';
                            }
                        }
                        
                        echo '</tbody></table>';
                    }
                }
                
                // List log files from utg-logs directory
                if (file_exists($utg_logs_dir)) {
                    $utg_log_files = glob($utg_logs_dir . '/*');
                    
                    if (!empty($utg_log_files)) {
                        echo '<h4>' . esc_html__('UTG Logs Directory', 'url-to-gutenberg') . '</h4>';
                        echo '<table class="widefat">';
                        echo '<thead><tr><th>' . esc_html__('Log File', 'url-to-gutenberg') . '</th><th>' . esc_html__('Date', 'url-to-gutenberg') . '</th><th>' . esc_html__('Size', 'url-to-gutenberg') . '</th><th>' . esc_html__('Actions', 'url-to-gutenberg') . '</th></tr></thead>';
                        echo '<tbody>';
                        
                        foreach ($utg_log_files as $log_file) {
                            if (is_file($log_file)) {
                                $file_name = basename($log_file);
                                $file_time = filemtime($log_file);
                                $file_size = size_format(filesize($log_file));
                                
                                echo '<tr>';
                                echo '<td>' . esc_html($file_name) . '</td>';
                                echo '<td>' . esc_html(date('Y-m-d H:i:s', $file_time)) . '</td>';
                                echo '<td>' . esc_html($file_size) . '</td>';
                                echo '<td><a href="#" class="utg-view-log" data-file="' . esc_attr($log_file) . '">' . esc_html__('View', 'url-to-gutenberg') . '</a></td>';
                                echo '</tr>';
                            }
                        }
                        
                        echo '</tbody></table>';
                    }
                }
                
                if (!file_exists($wc_logs_dir) && !file_exists($utg_logs_dir)) {
                    echo '<div class="notice notice-warning inline"><p>' . esc_html__('No log directories found.', 'url-to-gutenberg') . '</p></div>';
                } elseif (empty($log_files) && empty($utg_log_files)) {
                    echo '<div class="notice notice-info inline"><p>' . esc_html__('No log files found.', 'url-to-gutenberg') . '</p></div>';
                }
                ?>
            </div>
            
        <?php elseif ($active_tab === 'extracted'): ?>
            <!-- Extracted Articles Tab -->
            <div class="utg-debug-section">
                <h3><?php esc_html_e('Extracted Articles', 'url-to-gutenberg'); ?></h3>
                <p><?php esc_html_e('View the HTML content extracted from URLs before processing.', 'url-to-gutenberg'); ?></p>
                
                <?php
                // List extracted article files
                if (file_exists($utg_debug_dir)) {
                    $extracted_files = glob($utg_debug_dir . '/*-extracted_article-*.html');
                    
                    if (!empty($extracted_files)) {
                        echo '<table class="widefat">';
                        echo '<thead><tr><th>' . esc_html__('File', 'url-to-gutenberg') . '</th><th>' . esc_html__('Date', 'url-to-gutenberg') . '</th><th>' . esc_html__('Size', 'url-to-gutenberg') . '</th><th>' . esc_html__('Actions', 'url-to-gutenberg') . '</th></tr></thead>';
                        echo '<tbody>';
                        
                        foreach ($extracted_files as $file) {
                            $file_name = basename($file);
                            $file_time = filemtime($file);
                            $file_size = size_format(filesize($file));
                            
                            echo '<tr>';
                            echo '<td>' . esc_html($file_name) . '</td>';
                            echo '<td>' . esc_html(date('Y-m-d H:i:s', $file_time)) . '</td>';
                            echo '<td>' . esc_html($file_size) . '</td>';
                            echo '<td><a href="#" class="utg-view-extracted" data-file="' . esc_attr($file) . '">' . esc_html__('View', 'url-to-gutenberg') . '</a></td>';
                            echo '</tr>';
                        }
                        
                        echo '</tbody></table>';
                    } else {
                        echo '<div class="notice notice-info inline"><p>' . esc_html__('No extracted article files found.', 'url-to-gutenberg') . '</p></div>';
                    }
                } else {
                    echo '<div class="notice notice-warning inline"><p>' . esc_html__('Debug directory not found.', 'url-to-gutenberg') . '</p></div>';
                }
                ?>
            </div>
            
        <?php elseif ($active_tab === 'llm'): ?>
            <!-- LLM Output Tab -->
            <div class="utg-debug-section">
                <h3><?php esc_html_e('LLM Output', 'url-to-gutenberg'); ?></h3>
                <p><?php esc_html_e('View the JSON content generated by the LLM API.', 'url-to-gutenberg'); ?></p>
                
                <?php
                // List LLM output files
                if (file_exists($utg_debug_dir)) {
                    $llm_files = glob($utg_debug_dir . '/*-final_content-*.json');
                    
                    if (!empty($llm_files)) {
                        echo '<table class="widefat">';
                        echo '<thead><tr><th>' . esc_html__('File', 'url-to-gutenberg') . '</th><th>' . esc_html__('Date', 'url-to-gutenberg') . '</th><th>' . esc_html__('Size', 'url-to-gutenberg') . '</th><th>' . esc_html__('Actions', 'url-to-gutenberg') . '</th></tr></thead>';
                        echo '<tbody>';
                        
                        foreach ($llm_files as $file) {
                            $file_name = basename($file);
                            $file_time = filemtime($file);
                            $file_size = size_format(filesize($file));
                            
                            echo '<tr>';
                            echo '<td>' . esc_html($file_name) . '</td>';
                            echo '<td>' . esc_html(date('Y-m-d H:i:s', $file_time)) . '</td>';
                            echo '<td>' . esc_html($file_size) . '</td>';
                            echo '<td><a href="#" class="utg-view-llm" data-file="' . esc_attr($file) . '">' . esc_html__('View', 'url-to-gutenberg') . '</a></td>';
                            echo '</tr>';
                        }
                        
                        echo '</tbody></table>';
                    } else {
                        echo '<div class="notice notice-info inline"><p>' . esc_html__('No LLM output files found.', 'url-to-gutenberg') . '</p></div>';
                    }
                } else {
                    echo '<div class="notice notice-warning inline"><p>' . esc_html__('Debug directory not found.', 'url-to-gutenberg') . '</p></div>';
                }
                ?>
            </div>
            
            <!-- Add direct LLM testing section on the LLM tab -->
            <div class="utg-debug-section" style="margin-top: 30px; background: #f8f8f8; padding: 15px; border-left: 4px solid #2271b1;">
                <h3><?php esc_html_e('Direct LLM Testing', 'url-to-gutenberg'); ?></h3>
                <p><?php esc_html_e('Test the latest LLM response directly without going through extraction steps.', 'url-to-gutenberg'); ?></p>
                
                <?php
                // Find the most recent LLM output file
                $latest_llm_file = null;
                $latest_time = 0;
                
                if (file_exists($utg_debug_dir)) {
                    $llm_files = glob($utg_debug_dir . '/*-final_content-*.json');
                    
                    foreach ($llm_files as $file) {
                        $file_time = filemtime($file);
                        if ($file_time > $latest_time) {
                            $latest_time = $file_time;
                            $latest_llm_file = $file;
                        }
                    }
                    
                    if ($latest_llm_file) {
                        echo '<div style="margin-top: 15px;">';
                        echo '<strong>' . esc_html__('Latest LLM File:', 'url-to-gutenberg') . '</strong> ';
                        echo esc_html(basename($latest_llm_file)) . ' (' . esc_html(date('Y-m-d H:i:s', $latest_time)) . ')';
                        echo '</div>';
                        
                        echo '<div style="margin-top: 10px;">';
                        echo '<button id="test-latest-llm" class="button button-primary" data-file="' . esc_attr($latest_llm_file) . '">';
                        echo esc_html__('Test Latest LLM Response', 'url-to-gutenberg');
                        echo '</button>';
                        echo '</div>';
                    } else {
                        echo '<div class="notice notice-info inline"><p>' . esc_html__('No LLM output files found.', 'url-to-gutenberg') . '</p></div>';
                    }
                }
                ?>
                
                <div id="llm-test-result" style="margin-top: 20px; display: none;">
                    <h4><?php esc_html_e('Test Result', 'url-to-gutenberg'); ?></h4>
                    <div id="llm-test-content" style="background: #fff; padding: 15px; border: 1px solid #ddd; max-height: 500px; overflow: auto;"></div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- File Viewer Modal -->
    <div id="utg-file-viewer-modal" class="utg-modal">
        <div class="utg-modal-content">
            <div class="utg-modal-header">
                <span class="utg-modal-close">&times;</span>
                <h2 id="utg-file-viewer-title">File Viewer</h2>
                <div id="utg-view-mode-controls" style="display: none;">
                    <label class="utg-radio-label">
                        <input type="radio" name="view-mode" value="code" checked> Code View
                    </label>
                    <label class="utg-radio-label">
                        <input type="radio" name="view-mode" value="render"> Rendered View
                    </label>
                </div>
            </div>
            <div class="utg-modal-body">
                <pre id="utg-file-viewer-content"></pre>
                <iframe id="utg-html-renderer" style="display: none; width: 100%; height: 800px; border: 1px solid #ddd;"></iframe>
            </div>
        </div>
    </div>
</div>

<style>
/* Modal Styles */
.utg-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.4);
}

.utg-modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 80%;
    max-width: 1200px;
    max-height: 90vh;
    overflow: auto;
    border-radius: 4px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.utg-modal-header {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
    border-bottom: 1px solid #ddd;
    padding-bottom: 10px;
}

.utg-modal-header h2 {
    margin: 0;
    flex-grow: 1;
}

.utg-modal-close {
    color: #aaa;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    margin-right: 10px;
}

.utg-modal-close:hover {
    color: #555;
}

.utg-modal-body {
    max-height: calc(90vh - 100px);
    overflow: auto;
}

#utg-file-viewer-content {
    white-space: pre-wrap;
    word-wrap: break-word;
    background-color: #f8f9fa;
    padding: 10px;
    border-radius: 4px;
    border: 1px solid #ddd;
    font-family: monospace;
    max-height: 800px;
    overflow: auto;
}

.utg-radio-label {
    margin-left: 15px;
    font-weight: normal;
    font-size: 14px;
}

.hljs {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 4px;
}

.min-height-30px {
    min-height: 30px;
}

.utg-debug-section {
    margin-top: 20px;
}

.utg-debug-section h3 {
    margin-bottom: 15px;
}

/* Table styles */
.widefat {
    width: 100%;
    margin-top: 10px;
    border-collapse: collapse;
}

.widefat th {
    padding: 8px;
    background-color: #f0f0f1;
    text-align: left;
    font-weight: 600;
}

.widefat td {
    padding: 8px;
    border-bottom: 1px solid #ddd;
}

.widefat tr:hover {
    background-color: #f9f9f9;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Initialize highlight.js for syntax highlighting
    if (typeof hljs !== 'undefined') {
        hljs.highlightAll();
    }
    
    // Debug console messages to help troubleshoot
    console.log('Debug dashboard script loaded');
    
    // View file click handler - using direct jQuery event binding without relying on class names
    $(document).on('click', 'a[data-file]', function(e) {
        e.preventDefault();
        console.log('View file link clicked');
        
        // Get the file path from the data attribute
        const filePath = $(this).data('file');
        if (!filePath) {
            console.error('No file path found for this link');
            return;
        }
        
        console.log('Opening file:', filePath);
        openFileViewer(filePath);
    });
    
    // Test latest LLM response
    $('#test-latest-llm').on('click', function() {
        const filePath = $(this).data('file');
        if (!filePath) {
            console.error('No LLM file path found');
            return;
        }
        
        console.log('Testing LLM file:', filePath);
        
        // Show loading state
        $('#llm-test-result').show();
        $('#llm-test-content').html('<p>Loading and processing LLM response...</p>');
        
        // Make AJAX request to process LLM file
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'utg_test_llm_response',
                security: '<?php echo wp_create_nonce('utg_test_llm_response'); ?>',
                file_path: filePath
            },
            success: function(response) {
                if (response.success) {
                    // Display the processed content
                    $('#llm-test-content').html(response.data);
                } else {
                    // Display error message
                    $('#llm-test-content').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                }
            },
            error: function(xhr, status, error) {
                $('#llm-test-content').html('<div class="notice notice-error"><p>Error: ' + error + '</p></div>');
                console.error('AJAX error:', xhr.responseText);
            }
        });
    });
    
    // Function to open file viewer modal
    function openFileViewer(filePath) {
        const fileName = filePath.split('/').pop();
        
        // Determine if this is an HTML file
        const isHtmlFile = fileName.toLowerCase().endsWith('.html') || 
                          fileName.toLowerCase().includes('extracted') ||
                          fileName.toLowerCase().includes('content');
        
        // Show a loading state
        $('#utg-file-viewer-title').text('Loading...');
        $('#utg-file-viewer-content').html('<p>Loading file content...</p>');
        $('#utg-file-viewer-modal').show();
        
        // Show/hide view mode controls based on file type
        if (isHtmlFile) {
            $('#utg-view-mode-controls').show();
            // Default to code view
            $('input[name="view-mode"][value="code"]').prop('checked', true);
            $('#utg-file-viewer-content').show();
            $('#utg-html-renderer').hide();
        } else {
            $('#utg-view-mode-controls').hide();
        }
        
        console.log('Making AJAX request for file:', filePath);
        
        // Make AJAX request to get file content
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'utg_read_debug_file',
                security: '<?php echo wp_create_nonce('utg_read_debug_file'); ?>',
                file_path: filePath
            },
            success: function(response) {
                console.log('AJAX response received:', response);
                $('#utg-file-viewer-title').text(fileName);
                
                if (response.success) {
                    const content = response.data;
                    
                    // Handle different file formats
                    if (isHtmlFile) {
                        // Store original HTML content
                        $('#utg-file-viewer-content').data('html-content', content);
                        
                        // Display formatted HTML code
                        const escapedContent = $('<div>').text(content).html();
                        $('#utg-file-viewer-content').html('<code class="language-html">' + escapedContent + '</code>');
                        if (typeof hljs !== 'undefined') {
                            hljs.highlightElement($('#utg-file-viewer-content code')[0]);
                        }
                        
                        // Also prepare the iframe with content in case user switches to render view
                        const iframe = document.getElementById('utg-html-renderer');
                        const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                        iframeDoc.open();
                        iframeDoc.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><style>body{font-family:sans-serif;line-height:1.6;color:#333;max-width:1000px;margin:0 auto;padding:20px;} img{max-width:100%;height:auto;} h1,h2,h3{margin-top:1.5em;}</style></head><body>' + content + '</body></html>');
                        iframeDoc.close();
                    } else if (fileName.toLowerCase().endsWith('.json')) {
                        // Pretty print JSON
                        try {
                            const jsonObj = JSON.parse(content);
                            const formattedJson = JSON.stringify(jsonObj, null, 2);
                            $('#utg-file-viewer-content').html('<code class="language-json">' + $('<div>').text(formattedJson).html() + '</code>');
                            if (typeof hljs !== 'undefined') {
                                hljs.highlightElement($('#utg-file-viewer-content code')[0]);
                            }
                        } catch (e) {
                            // If not valid JSON, display as text
                            $('#utg-file-viewer-content').html('<code>' + $('<div>').text(content).html() + '</code>');
                        }
                    } else {
                        // Regular text files
                        $('#utg-file-viewer-content').html('<code>' + $('<div>').text(content).html() + '</code>');
                    }
                } else {
                    $('#utg-file-viewer-content').html('<div class="utg-error">' + response.data + '</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', xhr);
                $('#utg-file-viewer-title').text('Error');
                $('#utg-file-viewer-content').html('<div class="utg-error">Failed to load file: ' + error + '</div>');
            }
        });
    }
    
    // Close modal when clicking the X
    $('.utg-modal-close').on('click', function() {
        $('.utg-modal').hide();
    });
    
    // Close modal when clicking outside
    $(window).on('click', function(e) {
        if ($(e.target).hasClass('utg-modal')) {
            $('.utg-modal').hide();
        }
    });
    
    // Handle view mode toggle
    $('input[name="view-mode"]').on('change', function() {
        const viewMode = $(this).val();
        
        if (viewMode === 'code') {
            $('#utg-file-viewer-content').show();
            $('#utg-html-renderer').hide();
        } else if (viewMode === 'render') {
            $('#utg-file-viewer-content').hide();
            $('#utg-html-renderer').show();
        }
    });
});
</script> 