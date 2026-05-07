/**
 * AI Elementor Builder – Editor Panel
 * Kompatibilis Elementor 3.30+ konténer-alapú felülettel.
 */
(function ($) {
    'use strict';

    let panelInjected = false;

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
                ⚡ Elementor <strong>Free</strong> aktív — Flip Box, Animated Headline és egyéb Pro widgetek nem elérhetők. <a href="https://elementor.com/pro/" target="_blank" style="color:#e67e22;">Frissíts Pro-ra</a>
               </div>`
            : `<div style="background:rgba(39,174,96,0.10);border:1px solid rgba(39,174,96,0.30);border-radius:6px;padding:8px 10px;margin-bottom:10px;font-size:11px;color:#27ae60;line-height:1.5;">
                ✅ Elementor <strong>Pro</strong> aktív — összes widget elérhető.
               </div>`;

        const panelHTML = `
            <div id="aie-panel" style="
                position: fixed;
                bottom: 20px;
                right: 20px;
                width: 370px;
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
                        <label style="color:#8899aa;font-size:10px;text-transform:uppercase;letter-spacing:.8px;font-weight:600;">
                            Mód
                        </label>
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
                    <textarea id="aie-prompt" placeholder="Pl.: Készíts egy modern fogorvosi landing page-t időpontfoglalással, szolgáltatásokkal és véleményekkel..." style="
                        width: 100%;
                        height: 110px;
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

                    <!-- Generálás gomb -->
                    <button id="aie-generate" type="button" style="
                        width: 100%;
                        margin-top: 10px;
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
                    ">
                        ✨ Generálás
                    </button>

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

        // Textarea focus style
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
    }

    // ── AI generálás ──────────────────────────────────────────────────────────

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
        if (!window.AIEData || !AIEData.restUrl) {
            setStatus('❌ AIEData hiányzik – újratöltés szükséges.', '#e74c3c');
            return;
        }

        setStatus('🔄 Prémium oldal generálása (15–45 mp)...', '#3498db');
        setLoading(true);

        $.ajax({
            url:         AIEData.restUrl,
            method:      'POST',
            beforeSend:  function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', AIEData.nonce);
            },
            contentType: 'application/json',
            data:        JSON.stringify({ post_id: postId, prompt: prompt, mode: mode }),
            timeout:     180000,
            success:     function (response) {
                if (response.success) {
                    setStatus('✅ Kész! Az oldal betöltése folyamatban...', '#27ae60');
                    setTimeout(reloadElementorEditor, 1200);
                } else {
                    setStatus('❌ ' + (response.message || 'Ismeretlen hiba.'), '#e74c3c');
                }
            },
            error: function (xhr, textStatus, errorThrown) {
                let msg;
                if (xhr.responseJSON) {
                    msg = xhr.responseJSON.message || xhr.responseJSON.code || JSON.stringify(xhr.responseJSON);
                } else if (xhr.responseText && xhr.responseText.length < 300) {
                    msg = xhr.responseText;
                } else {
                    msg = 'HTTP ' + xhr.status + ' – ' + (errorThrown || textStatus || 'Hálózati hiba');
                }
                setStatus('❌ ' + msg, '#e74c3c');
                console.error('[AIE] Hiba:', xhr.status, textStatus, errorThrown, xhr.responseText ? xhr.responseText.substring(0, 500) : '');
            },
            complete: function () {
                setLoading(false);
            },
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
