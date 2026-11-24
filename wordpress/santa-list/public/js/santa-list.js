/* Santa's List - Public JS */
(function ($) {
    'use strict';

    var settings = santaList.settings;

    function showMessage(msg, type) {
        var $el = $('#santa-list-messages');
        $el.html('<div class="santa-list-notice santa-list-notice--' + type + '">' + msg + '</div>');
        $el[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function setLoading(loading) {
        var $btn = $('#santa-list-submit');
        $btn.prop('disabled', loading);
        $btn.text(loading ? santaList.i18n.submitting : '🎅 ' + (window.santaListI18n ? santaListI18n.submit : "Add to Santa's List"));
    }

    function getLocation() {
        return new Promise(function (resolve, reject) {
            if (!navigator.geolocation) {
                reject(new Error('Geolocation is not supported by your browser.'));
                return;
            }
            showMessage(santaList.i18n.location_prompt, 'info');
            navigator.geolocation.getCurrentPosition(
                function (pos) { resolve(pos.coords); },
                function ()    { reject(new Error(santaList.i18n.location_denied)); },
                { timeout: 10000, maximumAge: 60000 }
            );
        });
    }

    function submitForm(formData) {
        return $.ajax({
            url: santaList.restUrl + '/submit',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(formData),
        });
    }

    $('#santa-list-form').on('submit', function (e) {
        e.preventDefault();

        var firstName   = $.trim($('#sl_first_name').val());
        var lastInitial = $.trim($('#sl_last_initial').val());
        var listType    = $('input[name="list_type"]:checked').val();
        var entryCode   = $.trim($('#sl_entry_code').val());

        if (!firstName || !lastInitial) {
            showMessage('Please enter the child\'s first name and last initial.', 'error');
            return;
        }

        if (!/^[A-Za-z]+$/.test(firstName)) {
            showMessage('First name may only contain letters.', 'error');
            return;
        }

        if (!/^[A-Za-z]$/.test(lastInitial)) {
            showMessage('Last initial must be a single letter.', 'error');
            return;
        }

        if (settings.requires_code && !entryCode) {
            showMessage('Please enter the event code.', 'error');
            return;
        }

        setLoading(true);
        $('#santa-list-messages').html('');

        var formData = {
            first_name:   firstName,
            last_initial: lastInitial,
            list_type:    listType,
            entry_code:   entryCode,
            nonce:        santaList.nonce,
        };

        var doSubmit = function (coords) {
            if (coords) {
                formData.latitude  = coords.latitude;
                formData.longitude = coords.longitude;
            }

            submitForm(formData)
                .done(function (res) {
                    setLoading(false);
                    if (res.success) {
                        showMessage(res.message, 'success');
                        $('#santa-list-form')[0].reset();
                    } else {
                        showMessage(res.message || 'Something went wrong. Please try again.', 'error');
                    }
                })
                .fail(function (xhr) {
                    setLoading(false);
                    var msg = 'Something went wrong. Please try again.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    showMessage(msg, 'error');
                });
        };

        if (settings.requires_geo) {
            getLocation()
                .then(function (coords) { doSubmit(coords); })
                .catch(function (err)   {
                    setLoading(false);
                    showMessage(err.message, 'error');
                });
        } else {
            doSubmit(null);
        }
    });

    // Auto-uppercase last initial
    $('#sl_last_initial').on('input', function () {
        this.value = this.value.toUpperCase().replace(/[^A-Z]/g, '').substring(0, 1);
    });

    // Capitalize first letter of first name
    $('#sl_first_name').on('blur', function () {
        var v = $.trim(this.value);
        if (v) this.value = v.charAt(0).toUpperCase() + v.slice(1).toLowerCase();
    });

}(jQuery));
