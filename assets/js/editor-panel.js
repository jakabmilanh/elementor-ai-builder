/**
 * AI Elementor Builder – Editor Panel
 * Async generation + polling, referencia képek, progress bar.
 */
(function ($) {
    'use strict';

    let panelInjected    = false;
    let referenceImages  = [];   // [{id, url, thumb}]

    $(document).ready(tryInit);
    $(window).on('elementor:init', tryInit);
    $(window).on('load', tryInit);
    setTimeout(tryInit, 2000);

    function tryInit() {
        if (panelInjected) return;
        if (!document.body) return;
        panelInjected = true;
        injectPanelHTML();
        bindEvents();
    }

    // ── UI injektálás ─────────────────────────────────────────────────────────

    function injectPanelHTML() {
        const hasPro   = window.AIEData && AIEData.hasElPro;
        const proBadge = hasPro
            ? '<span style="background:#27ae60;color:#fff;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;letter-spacing:.5px;">PRO</span>'
            : '<span style="background:#e67e22;color:#fff;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;letter-spacing:.5px;">FREE</span>';

        const proNotice = !hasPro
            ? `<div style="background:rgba(230,126,34,0.12);border:1px solid rgba(230,126,34,0.35);border-radius:6px;padding:8px 10px;margin-bottom:10px;font-size:11px;color:#e67e22;line-height:1.5;">
                ⚡ Elementor <strong>Free</strong> aktív — Flip Box, Animated Headline és egyéb Pro widgetek nem elérhetők.
               </div>`
            : `<div style="background:rgba(39,174,96,0.10);border:1px solid rgba(39,174,96,0.30);border-radius:6px;padding:8px 10px;margin-bottom:10px;font-size:11px;color:#27ae60;line-height:1.5;">
                ✅ Elementor <strong>Pro</strong> aktív — összes widget elérhető.
               </div>`;

        const panelHTML = `
            <div id="aie-panel" style="
                position: fixed;
                bottom: 20px;
                right: 20px;
                width: 380px;
                background: #12122a;
                border: 1px solid rgba(233,69,96,0.5);
                border-radius: 14px;
                box-shadow: 0 12px 48px rgba(0,0,0,0.6);
                z-index: 99999;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                overflow: hidden;
            ">
                <!-- Header -->
                <div style="
                    background: linear-gradient(135deg, #e94560 0%, #0f3460 100%);
                    padding: 13px 16px;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    cursor: move;
                    user-select: none;
                " id="aie-panel-header">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span style="font-size:16px;">🤖</span>
                        <strong style="color:#fff;font-size:13px;letter-spacing:.3px;">AI Elementor Builder</strong>
                        ${proBadge}
                    </div>
                    <button id="aie-toggle" type="button" style="
                        background:none;border:none;color:#fff;cursor:pointer;
                        font-size:20px;line-height:1;padding:0;width:24px;text-align:center;
                    ">−</button>
                </div>

                <!-- Body -->
                <div id="aie-panel-body" style="padding:14px 16px 16px;">

                    ${proNotice}

                    <!-- Mód -->
                    <div style="margin-bottom:12px;">
                        <label style="color:#8899aa;font-size:10px;text-transform:uppercase;letter-spacing:.8px;font-weight:600;">Mód</label>
                        <div style="display:flex;gap:14px;margin-top:6px;">
                            <label style="color:#ccd;font-size:12px;cursor:pointer;display:flex;align-items:center;gap:4px;">
                                <input type="radio" name="aie-mode" value="auto" checked> Auto
                            </label>
                            <label style="color:#ccd;font-size:12px;cursor:pointer;display:flex;align-items:center;gap:4px;">
                                <input type="radio" name="aie-mode" value="create"> Új oldal
                            </label>
                            <label style="color:#ccd;font-size:12px;cursor:pointer;display:flex;align-items:center;gap:4px;">
                                <input type="radio" name="aie-mode" value="modify"> Módosít
                            </label>
                        </div>
                    </div>

                    <!-- Prompt -->
                    <textarea id="aie-prompt" placeholder="Pl.: Készíts egy modern fogorvosi landing page-t kék-fehér színekkel, időpontfoglalással és véleményekkel..." style="
                        width: 100%;
                        height: 90px;
                        background: #0c0c1e;
                        border: 1px solid #2a2a4a;
                        border-radius: 8px;
                        color: #ddeeff;
                        font-size: 13px;
                        line-height: 1.5;
                        padding: 10px 12px;
                        resize: vertical;
                        box-sizing: border-box;
                        font-family: inherit;
                        transition: border-color .2s;
                        outline: none;
                    "></textarea>

                    <!-- Referencia képek -->
                    <div style="margin-top:10px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                            <label style="color:#8899aa;font-size:10px;text-transform:uppercase;letter-spacing:.8px;font-weight:600;">
                                Design referencia
                            </label>
                            <button id="aie-add-ref-img" type="button" style="
                                background:rgba(233,69,96,0.15);border:1px solid rgba(233,69,96,0.4);
                                color:#e94560;border-radius:5px;padding:3px 10px;font-size:11px;
                                cursor:pointer;font-weight:600;
                            ">+ Kép hozzáadása</button>
                        </div>
                        <div id="aie-ref-thumbnails" style="display:flex;gap:6px;flex-wrap:wrap;min-height:0;"></div>
                        <div style="font-size:10px;color:#445;margin-top:4px;line-height:1.4;">
                            Adj meg max. 3 képet amit az AI design inspirációként használ.
                        </div>
                    </div>

                    <!-- Generálás gomb -->
                    <button id="aie-generate" type="button" style="
                        width: 100%;
                        margin-top: 12px;
                        background: linear-gradient(135deg, #e94560, #c73652);
                        color: #fff;
                        border: none;
                        border-radius: 8px;
                        padding: 13px;
                        font-size: 14px;
                        font-weight: 700;
                        cursor: pointer;
                        letter-spacing: .3px;
                        transition: opacity .2s, transform .1s;
                    ">✨ Generálás</button>

                    <!-- Progress bar -->
                    <div id="aie-progress-wrap" style="display:none;margin-top:10px;">
                        <div style="height:3px;background:#1a1a3a;border-radius:2px;overflow:hidden;">
                            <div id="aie-progress-bar" style="height:100%;background:linear-gradient(90deg,#e94560,#c73652);width:0%;transition:width .5s ease;border-radius:2px;"></div>
                        </div>
                    </div>

                    <!-- Tipp -->
                    <div style="margin-top:8px;font-size:11px;color:#556;text-align:center;">
                        Ctrl+Enter a gyors generáláshoz
                    </div>

                    <!-- Státusz -->
                    <div id="aie-status" style="
                        margin-top: 10px;
                        font-size: 12px;
                        color: #8899aa;
                        min-height: 18px;
                        word-break: break-word;
                        line-height: 1.5;
                    "></div>
                </div>
            </div>
        `;

        const mount = document.getElementById('aie-panel-mount');
        if (mount) {
            mount.innerHTML = panelHTML;
        } else {
            $('body').append(panelHTML);
        }

        const panel  = document.getElementById('aie-panel');
        const handle = document.getElementById('aie-panel-header');
        if (panel && handle) makeDraggable(panel, handle);

        $(document).on('focus', '#aie-prompt', function () {
            $(this).css('border-color', '#e94560');
        }).on('blur', '#aie-prompt', function () {
            $(this).css('border-color', '#2a2a4a');
        });
    }

    // ── Event binding ─────────────────────────────────────────────────────────

    function bindEvents() {
        $(document).on('click', '#aie-toggle', function () {
            const $body = $('#aie-panel-body');
            const open  = $body.is(':visible');
            $body.slideToggle(150);
            $(this).text(open ? '+' : '−');
        });

        $(document).on('click', '#aie-generate', handleGenerate);

        $(document).on('keydown', '#aie-prompt', function (e) {
            if (e.ctrlKey && e.key === 'Enter') handleGenerate();
        });

        // Referencia kép hozzáadása
        $(document).on('click', '#aie-add-ref-img', openMediaPicker);

        // Referencia kép törlése
        $(document).on('click', '.aie-ref-remove', function () {
            const id = parseInt($(this).data('id'), 10);
            referenceImages = referenceImages.filter(img => img.id !== id);
            renderThumbnails();
        });
    }

    // ── Média könyvtár picker ─────────────────────────────────────────────────

    function openMediaPicker() {
        if (referenceImages.length >= 3) {
            setStatus('⚠️ Maximum 3 referencia kép adható meg.', '#f39c12');
            return;
        }

        // wp.media elérhetőség ellenőrzése
        if (typeof wp === 'undefined' || !wp.media) {
            setStatus('⚠️ WordPress média könyvtár nem elérhető.', '#f39c12');
            return;
        }

        const frame = wp.media({
            title:    'Válassz design referencia képet',
            button:   { text: 'Referenciaként használom' },
            multiple: false,
            library:  { type: 'image' },
        });

        frame.on('select', function () {
            const attachment = frame.state().get('selection').first().toJSON();

            // Duplikátum ellenőrzés
            if (referenceImages.find(img => img.id === attachment.id)) return;

            const thumb = attachment.sizes && attachment.sizes.thumbnail
                ? attachment.sizes.thumbnail.url
                : attachment.url;

            referenceImages.push({
                id:    attachment.id,
                url:   attachment.url,
                thumb: thumb,
                title: attachment.title || '',
            });

            renderThumbnails();
        });

        frame.open();
    }

    function renderThumbnails() {
        const $container = $('#aie-ref-thumbnails');
        $container.empty();

        referenceImages.forEach(function (img) {
            $container.append(`
                <div style="position:relative;width:64px;height:64px;border-radius:6px;overflow:hidden;border:1px solid rgba(233,69,96,0.4);">
                    <img src="${img.thumb}" alt="${img.title}" style="width:100%;height:100%;object-fit:cover;">
                    <button class="aie-ref-remove" data-id="${img.id}" type="button" style="
                        position:absolute;top:1px;right:1px;
                        background:rgba(0,0,0,0.75);border:none;color:#fff;
                        border-radius:3px;width:18px;height:18px;font-size:12px;
                        line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;
                        padding:0;
                    ">×</button>
                </div>
            `);
        });
    }

    // ── AI generálás (async + polling) ───────────────────────────────────────

    function handleGenerate() {
        const prompt = $('#aie-prompt').val().trim();
        const mode   = $('input[name="aie-mode"]:checked').val();
        const postId = (window.AIEData && AIEData.postId) || getPostIdFromURL();

        if (!prompt) {
            setStatus('⚠️ Kérlek írj be egy promptot!', '#f39c12');
            return;
        }
        if (!postId) {
            setStatus('❌ Nem található az oldal ID. Mentsd el az oldalt először!', '#e74c3c');
            return;
        }
        if (!window.AIEData || !AIEData.restBase) {
            setStatus('❌ AIEData hiányzik – újratöltés szükséges.', '#e74c3c');
            return;
        }

        setStatus('🔄 Generálás indítása...', '#3498db');
        setLoading(true);
        setProgress(5);

        const refImageIds = referenceImages.map(img => img.id);

        $.ajax({
            url:         AIEData.restBase + '/generate-async',
            method:      'POST',
            beforeSend:  function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', AIEData.nonce);
            },
            contentType: 'application/json',
            data:        JSON.stringify({
                post_id:          postId,
                prompt:           prompt,
                mode:             mode,
                reference_images: refImageIds,
            }),
            timeout:     15000,
            success:     function (response) {
                if (response && response.job_id) {
                    setProgress(10);
                    setStatus('⏳ AI tervezi az oldalt...', '#3498db');
                    pollJobStatus(response.job_id, 0);
                } else {
                    setStatus('❌ Nem sikerült elindítani a generálást.', '#e74c3c');
                    setLoading(false);
                    setProgress(0);
                }
            },
            error: function (xhr, textStatus, errorThrown) {
                let msg;
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                } else if (xhr.responseText && xhr.responseText.length < 300) {
                    msg = xhr.responseText;
                } else {
                    msg = 'HTTP ' + xhr.status + ' – ' + (errorThrown || textStatus || 'Hálózati hiba');
                }
                setStatus('❌ ' + msg, '#e74c3c');
                setLoading(false);
                setProgress(0);
                console.error('[AIE] Async start error:', xhr.status, textStatus, errorThrown);
            }
        });
    }

    // ── Polling ───────────────────────────────────────────────────────────────

    function pollJobStatus(jobId, attempts) {
        if (attempts > 200) {
            setStatus('❌ Időtúllépés – a generálás túl sokáig tartott. Próbáld újra.', '#e74c3c');
            setLoading(false);
            setProgress(0);
            return;
        }

        // Progress animáció: 10% → 88% a várakozás alatt (200 kísérlet = 10 perc)
        var progressPct = Math.min(10 + attempts * 0.39, 88);
        setProgress(progressPct);

        $.ajax({
            url: AIEData.restBase + '/job-status/' + jobId,
            method: 'GET',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', AIEData.nonce);
            },
            timeout: 10000,
            success: function (res) {
                if (res.status === 'done') {
                    setProgress(100);
                    setStatus('✅ Kész! Az oldal betöltése folyamatban...', '#27ae60');
                    setLoading(false);
                    setTimeout(function () {
                        setProgress(0);
                        reloadElementorEditor();
                    }, 1200);

                } else if (res.status === 'error') {
                    setStatus('❌ ' + (res.message || 'Ismeretlen hiba.'), '#e74c3c');
                    setLoading(false);
                    setProgress(0);

                } else {
                    // pending / processing – szerver üzenet megjelenítése
                    if (res.message && res.message.length > 3) {
                        setStatus('⏳ ' + res.message, '#3498db');
                    }
                    setTimeout(function () {
                        pollJobStatus(jobId, attempts + 1);
                    }, 3000);
                }
            },
            error: function () {
                // Hálózati hiba – újrapróbál
                setTimeout(function () {
                    pollJobStatus(jobId, attempts + 1);
                }, 4000);
            }
        });
    }

    // ── Segédfüggvények ───────────────────────────────────────────────────────

    function setStatus(msg, color) {
        $('#aie-status').css('color', color || '#8899aa').html(msg);
    }

    function setLoading(isLoading) {
        const $btn = $('#aie-generate');
        $btn.prop('disabled', isLoading).css('opacity', isLoading ? 0.55 : 1);
        $btn.html(isLoading ? '⏳ Generálás folyamatban...' : '✨ Generálás');
    }

    function setProgress(pct) {
        const $wrap = $('#aie-progress-wrap');
        const $bar  = $('#aie-progress-bar');
        if (pct <= 0) {
            $wrap.hide();
            $bar.css('width', '0%');
        } else {
            $wrap.show();
            $bar.css('width', pct + '%');
        }
    }

    function getPostIdFromURL() {
        const params = new URLSearchParams(window.location.search);
        return parseInt(params.get('post') || '0', 10) || null;
    }

    function reloadElementorEditor() {
        try {
            if (window.elementor && window.elementor.documents && window.elementor.documents.getCurrent) {
                const doc = window.elementor.documents.getCurrent();
                if (doc && doc.refresh) { doc.refresh(); return; }
            }
        } catch (e) {
            console.warn('[AIE] Soft reload failed.', e);
        }
        window.location.reload();
    }

    // ── Draggable ─────────────────────────────────────────────────────────────

    function makeDraggable(panel, handle) {
        let offsetX = 0, offsetY = 0, isDragging = false;

        handle.addEventListener('mousedown', function (e) {
            if (e.target.id === 'aie-toggle') return;
            isDragging = true;
            const rect = panel.getBoundingClientRect();
            offsetX = e.clientX - rect.left;
            offsetY = e.clientY - rect.top;
            e.preventDefault();
        });

        document.addEventListener('mousemove', function (e) {
            if (!isDragging) return;
            panel.style.left   = (e.clientX - offsetX) + 'px';
            panel.style.top    = (e.clientY - offsetY) + 'px';
            panel.style.right  = 'auto';
            panel.style.bottom = 'auto';
        });

        document.addEventListener('mouseup', function () { isDragging = false; });
    }

}(jQuery));
