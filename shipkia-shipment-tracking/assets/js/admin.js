/* Admin JS */
jQuery(document).ready(function($) {
    // Copy button functionality with event delegation
    $(document).on('click', '.shipkia-copy-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var textToCopy = $(this).data('copy');
        var $btn = $(this);
        
        // Try modern clipboard API first
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(textToCopy).then(function() {
                showCopySuccess($btn);
            }).catch(function(err) {
                // Fallback to old method
                fallbackCopy(textToCopy, $btn);
            });
        } else {
            // Fallback for older browsers
            fallbackCopy(textToCopy, $btn);
        }
    });
    
    function fallbackCopy(text, $btn) {
        var $temp = $('<textarea>');
        $('body').append($temp);
        $temp.val(text).select();
        
        try {
            document.execCommand('copy');
            showCopySuccess($btn);
        } catch (err) {
            console.error('Failed to copy:', err);
            alert('Failed to copy. Please select and copy manually.');
        }
        
        $temp.remove();
    }
    
    function showCopySuccess($btn) {
        var originalHtml = $btn.html();
        $btn.html('✓ Copied');
        $btn.css('background-color', '#46b450');
        
        setTimeout(function() {
            $btn.html(originalHtml);
            $btn.css('background-color', '');
        }, 2000);
    }

    // ==================== Shipkia Connection Handlers ====================

    // Connect to Shipkia
    $(document).on('click', '#shipkia-connect-btn', function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var $input = $('#shipkia_app_url');
        var $message = $('#shipkia-connection-message');
        var appUrl = $input.val().trim();
        
        // Validate URL
        if (!appUrl) {
            showConnectionMessage('Please enter a Shipkia URL', 'error');
            return;
        }
        
        if (!isValidUrl(appUrl)) {
            showConnectionMessage('Please enter a valid URL (include http:// or https://)', 'error');
            return;
        }
        
        // Disable controls and show loading state
        $btn.prop('disabled', true).text('Connecting...');
        $input.prop('readonly', true).css('opacity', '0.6');
        $message.html('<p style="color: #0073aa;">Please wait, connecting to Shipkia...</p>');
        
        // Make AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'shipkia_connect_platform',
                app_url: appUrl,
                nonce: getConnectionNonce()
            },
            success: function(response) {
                if (response.success) {
                    showConnectionMessage(response.data.message || 'Connected successfully!', 'success');
                    // Lock fields permanently on success
                    $btn.text('Connected ✅');
                    // Reload page after a delay to show connected state
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    var errorMsg = response.data && response.data.message ? response.data.message : 'Connection failed. Please check the URL and try again.';
                    showConnectionMessage(errorMsg, 'error');
                    // Re-enable controls on failure
                    $btn.prop('disabled', false).text('Connect to Shipkia');
                    $input.prop('readonly', false).css('opacity', '1');
                }
            },
            error: function(xhr, status, error) {
                var detail = '';
                if (xhr.status === 404) detail = ' (API endpoint not found)';
                if (xhr.status === 500) detail = ' (Internal server error)';
                showConnectionMessage('Connection failed: ' + error + detail, 'error');
                // Re-enable controls on error
                $btn.prop('disabled', false).text('Connect to Shipkia');
                $input.prop('readonly', false).css('opacity', '1');
            }
        });
    });

    // Sync with Shipkia
    $(document).on('click', '#shipkia-sync-btn', function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var originalHtml = $btn.html();
        
        function performSync(createNew) {
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update shipkia-spin" style="font-size: 16px; vertical-align: middle; line-height: 28px;"></span> Syncing...');
            
            // Add CSS for rotation if not already present
            if (!$('#shipkia-spin-css').length) {
                $('head').append('<style id="shipkia-spin-css">.shipkia-spin { animation: shipkia-rotation 2s infinite linear; } @keyframes shipkia-rotation { from { transform: rotate(0deg); } to { transform: rotate(359deg); } }</style>');
            }
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'shipkia_sync_platform',
                    nonce: getConnectionNonce(),
                    create_new: createNew
                },
                success: function(response) {
                    if (response.success) {
                        showConnectionMessage(response.data.message || 'Sync successful', 'success');
                        $btn.html('<span class="dashicons dashicons-yes" style="font-size: 16px; vertical-align: middle; line-height: 28px;"></span> Synced');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        // Check for store not found special case
                        if (response.data.store_not_found) {
                            if (confirm('No existing Shipkia store found for this URL. Do you want to create a new store?')) {
                                performSync(true); // Retry with creation enabled
                            } else {
                                // User denied creation of missing store, so disconnect locally to match backend state
                                showConnectionMessage('Sync cancelled. Disconnecting...', 'info');
                                $.ajax({
                                    url: ajaxurl,
                                    type: 'POST',
                                    data: {
                                        action: 'shipkia_disconnect_platform',
                                        nonce: getConnectionNonce()
                                    },
                                    success: function() {
                                        location.reload();
                                    }
                                });
                            }
                        } else {
                            showConnectionMessage(response.data.message || 'Sync failed', 'error');
                            $btn.prop('disabled', false).html(originalHtml);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    showConnectionMessage('Sync failed: ' + error, 'error');
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        }
        
        // Start sync with create_new = false (check existence first)
        performSync(false);
    });

    // Disconnect from Shipkia
    $(document).on('click', '#shipkia-disconnect-btn', function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var $message = $('#shipkia-connection-message');
        
        if (!confirm('Are you sure you want to disconnect from Shipkia? This will clear all connection settings.')) {
            return;
        }
        
        $btn.prop('disabled', true).text('Disconnecting...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'shipkia_disconnect_platform',
                nonce: getConnectionNonce()
            },
            success: function(response) {
                if (response.success) {
                    showConnectionMessage(response.data.message || 'Disconnected successfully', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showConnectionMessage(response.data.message || 'Disconnect failed', 'error');
                    $btn.prop('disabled', false).text('Disconnect');
                }
            },
            error: function(xhr, status, error) {
                showConnectionMessage('Disconnect failed: ' + error, 'error');
                $btn.prop('disabled', false).text('Disconnect');
            }
        });
    });

    // Helper: Show connection message
    function showConnectionMessage(message, type) {
        var $message = $('#shipkia-connection-message');
        var className = type === 'success' ? 'notice notice-success' : 'notice notice-error';
        
        $message.html('<div class="' + className + ' inline"><p>' + message + '</p></div>');
        
        // Auto-hide after 5 seconds for success messages
        if (type === 'success') {
            setTimeout(function() {
                $message.fadeOut();
            }, 5000);
        }
    }

    // Helper: Validate URL
    function isValidUrl(url) {
        try {
            new URL(url);
            return true;
        } catch (e) {
            return false;
        }
    }

    // Helper: Get connection nonce
    function getConnectionNonce() {
        return typeof shipkiaAdmin !== 'undefined' ? shipkiaAdmin.nonce : '';
    }
});
