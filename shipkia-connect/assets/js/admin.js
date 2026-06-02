/* Admin JS */
jQuery(document).ready(function ($) {
    function getConnectionNonce() {
        return typeof shipkiaAdmin !== 'undefined' ? shipkiaAdmin.nonce : '';
    }

    function getAjaxUrl() {
        if (typeof shipkiaAdmin !== 'undefined' && shipkiaAdmin.ajaxurl) {
            return shipkiaAdmin.ajaxurl;
        }

        return typeof ajaxurl !== 'undefined' ? ajaxurl : '';
    }

    function escapeHtml(value) {
        return $('<div>').text(value || '').html();
    }

    function showConnectionMessage(message, type, selector) {
        var $message = $(selector || '#shipkia-connection-message');
        var className = type === 'success' ? 'notice-success' : (type === 'info' ? 'notice-info' : 'notice-error');

        $message.html('<div class="notice ' + className + ' inline"><p>' + escapeHtml(message) + '</p></div>').show();

        if (type === 'success') {
            setTimeout(function () {
                $message.fadeOut();
            }, 5000);
        }
    }

    function getAjaxErrorMessage(xhr, fallback) {
        if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
            return xhr.responseJSON.data.message;
        }

        if (xhr && xhr.status) {
            return fallback + ' (' + xhr.status + ')';
        }

        return fallback;
    }

    function isValidUrl(url) {
        try {
            new URL(url);
            return true;
        } catch (e) {
            return false;
        }
    }

    $(document).on('click', '.shipkia-copy-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var textToCopy = $(this).data('copy');
        var $btn = $(this);

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(textToCopy).then(function () {
                showCopySuccess($btn);
            }).catch(function () {
                fallbackCopy(textToCopy, $btn);
            });
        } else {
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
            alert('Failed to copy. Please select and copy manually.');
        }

        $temp.remove();
    }

    function showCopySuccess($btn) {
        var originalHtml = $btn.html();
        $btn.html('Copied').css('background-color', '#46b450');

        setTimeout(function () {
            $btn.html(originalHtml).css('background-color', '');
        }, 2000);
    }

    $(document).on('click', '#shipkia-connect-btn', function (e) {
        e.preventDefault();

        var $btn = $(this);
        var $input = $('#shipkia_app_url');
        var appUrl = $input.val().trim();

        if (!appUrl) {
            showConnectionMessage('Please enter a ShipKia URL', 'error');
            return;
        }

        if (!isValidUrl(appUrl)) {
            showConnectionMessage('Please enter a valid URL (include http:// or https://)', 'error');
            return;
        }

        $btn.prop('disabled', true).text('Connecting...');
        $input.prop('readonly', true).css('opacity', '0.6');
        showConnectionMessage('Please wait, connecting to ShipKia...', 'info');

        $.post(getAjaxUrl(), {
            action: 'shipkia_connect_platform',
            app_url: appUrl,
            nonce: getConnectionNonce()
        }, function (response) {
            if (response.success) {
                showConnectionMessage(response.data.message || 'Connected successfully!', 'success');
                $btn.text('Connected');
                setTimeout(function () { location.reload(); }, 1500);
            } else {
                showConnectionMessage(response.data && response.data.message ? response.data.message : 'Connection failed. Please check the URL and try again.', 'error');
                $btn.prop('disabled', false).text('Connect to ShipKia');
                $input.prop('readonly', false).css('opacity', '1');
            }
        }).fail(function (xhr) {
            showConnectionMessage(getAjaxErrorMessage(xhr, 'Connection failed. Please try again.'), 'error');
            $btn.prop('disabled', false).text('Connect to ShipKia');
            $input.prop('readonly', false).css('opacity', '1');
        });
    });

    $(document).on('click', '#shipkia-initialize-btn', function (e) {
        e.preventDefault();

        var $btn = $(this);
        $btn.prop('disabled', true).text('Initializing...');
        $('#shipkia-init-message').html('');

        function performInit(createNew) {
            $.post(getAjaxUrl(), {
                action: 'shipkia_sync_platform',
                create_new: createNew ? 1 : 0,
                nonce: getConnectionNonce()
            }, function (response) {
                if (response.success) {
                    showConnectionMessage(response.data.message || 'Connected!', 'success', '#shipkia-init-message');
                    setTimeout(function () { location.reload(); }, 1500);
                } else {
                    if (!createNew && response.data && (response.data.status === 'not_found' || response.data.store_not_found)) {
                        if (confirm(response.data.message || 'Store not found on ShipKia. Create a new connection?')) {
                            performInit(true);
                            return;
                        }
                    }

                    showConnectionMessage(response.data && response.data.message ? response.data.message : 'Initialization failed', 'error', '#shipkia-init-message');
                    $btn.prop('disabled', false).text('Initialize Store Connection');
                }
            }).fail(function (xhr) {
                showConnectionMessage(getAjaxErrorMessage(xhr, 'Network error. Please try again.'), 'error', '#shipkia-init-message');
                $btn.prop('disabled', false).text('Initialize Store Connection');
            });
        }

        performInit(false);
    });

    $(document).on('click', '#shipkia-connect-platform-btn', function (e) {
        e.preventDefault();

        if (!confirm('You are about to be redirected to the ShipKia platform/WooCommerce authorization page to grant permissions. Do you want to proceed?')) {
            return;
        }

        var $btn = $(this);
        var authWindow = window.open('', '_blank');
        $btn.prop('disabled', true).text('Redirecting...');

        $.post(getAjaxUrl(), {
            action: 'shipkia_get_auth_url',
            nonce: getConnectionNonce()
        }, function (response) {
            if (response.success && response.data.auth_url) {
                if (authWindow) {
                    authWindow.location = response.data.auth_url;
                } else {
                    window.location.href = response.data.auth_url;
                }
                $btn.prop('disabled', false).text('Connect Platform API');
                showConnectionMessage('Authorization opened in a new tab. Please complete it and then refresh this page.', 'info');
            } else {
                if (authWindow) {
                    authWindow.close();
                }
                showConnectionMessage(response.data && response.data.message ? response.data.message : 'Failed to initiate platform connection.', 'error');
                $btn.prop('disabled', false).text('Connect Platform API');
            }
        }).fail(function (xhr) {
            if (authWindow) {
                authWindow.close();
            }
            showConnectionMessage(getAjaxErrorMessage(xhr, 'Network error. Please try again.'), 'error');
            $btn.prop('disabled', false).text('Connect Platform API');
        });
    });

    $(document).on('click', '#shipkia-sync-btn', function (e) {
        e.preventDefault();

        var $btn = $(this);
        var originalHtml = $btn.html();

        function performSync(createNew) {
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update shipkia-button-icon shipkia-spin" aria-hidden="true"></span> Syncing...');

            $.post(getAjaxUrl(), {
                action: 'shipkia_sync_platform',
                nonce: getConnectionNonce(),
                create_new: createNew ? 1 : 0
            }, function (response) {
                if (response.success) {
                    showConnectionMessage(response.data.message || 'Sync successful', 'success');
                    $btn.html('<span class="dashicons dashicons-yes shipkia-button-icon" aria-hidden="true"></span> Synced');
                    setTimeout(function () { location.reload(); }, 1500);
                } else {
                    if (!createNew && response.data && (response.data.store_not_found || response.data.status === 'not_found')) {
                        if (confirm('No existing ShipKia store found for this URL. Do you want to create a new store?')) {
                            performSync(true);
                            return;
                        }

                        showConnectionMessage('Sync cancelled. Disconnecting...', 'info');
                        $.post(getAjaxUrl(), {
                            action: 'shipkia_disconnect_platform',
                            nonce: getConnectionNonce()
                        }, function () {
                            location.reload();
                        });
                        return;
                    }

                    showConnectionMessage(response.data && response.data.message ? response.data.message : 'Sync failed', 'error');
                    $btn.prop('disabled', false).html(originalHtml);
                }
            }).fail(function (xhr) {
                showConnectionMessage(getAjaxErrorMessage(xhr, 'Sync failed. Please try again.'), 'error');
                $btn.prop('disabled', false).html(originalHtml);
            });
        }

        performSync(false);
    });

    $(document).on('click', '#shipkia-disconnect-btn', function (e) {
        e.preventDefault();

        if (!confirm('Are you sure you want to disconnect? This will stop all synchronization. You can reconnect later.')) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text('Disconnecting...');

        $.post(getAjaxUrl(), {
            action: 'shipkia_disconnect_platform',
            nonce: getConnectionNonce()
        }, function (response) {
            if (response.success) {
                showConnectionMessage(response.data.message || 'Disconnected successfully', 'success');
                setTimeout(function () { location.reload(); }, 1500);
            } else {
                showConnectionMessage(response.data && response.data.message ? response.data.message : 'Disconnect failed', 'error');
                $btn.prop('disabled', false).text('Disconnect');
            }
        }).fail(function (xhr) {
            showConnectionMessage(getAjaxErrorMessage(xhr, 'Disconnect failed. Please try again.'), 'error');
            $btn.prop('disabled', false).text('Disconnect');
        });
    });

    $(document).on('click', '#shipkia-reconnect-btn', function (e) {
        e.preventDefault();

        var $btn = $(this);
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update shipkia-button-icon shipkia-spin" aria-hidden="true"></span> Reconnecting...');

        $.post(getAjaxUrl(), {
            action: 'shipkia_reconnect_platform',
            nonce: getConnectionNonce()
        }, function (response) {
            if (response.success) {
                showConnectionMessage(response.data.message || 'Reconnected successfully!', 'success');
                setTimeout(function () { location.reload(); }, 1500);
            } else {
                showConnectionMessage(response.data && response.data.message ? response.data.message : 'Reconnection failed', 'error');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-update shipkia-button-icon" aria-hidden="true"></span> Reconnect');
            }
        }).fail(function (xhr) {
            showConnectionMessage(getAjaxErrorMessage(xhr, 'Network error. Please try again.'), 'error');
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-update shipkia-button-icon" aria-hidden="true"></span> Reconnect');
        });
    });

    $(document).on('click', '#shipkia-sync-order-product-link', function () {
        $(this).hide();
        $.post(getAjaxUrl(), {
            action: 'shipkia_mark_sync_requested',
            nonce: getConnectionNonce()
        });
    });
});
