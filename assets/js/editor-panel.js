/**
 * AI Elementor Builder – Editor Panel
 * Kompatibilis Elementor 3.30+ konténer-alapú felülettel.
 */
(function ($) {
    'use strict';

    let panelInjected = false;

    // ── Több indítási stratégia – bármelyik először működik ───────────────────
    $(document).ready(tryInit);
    $(window).on('elementor:init', tryInit);
    $(window).on('load', tryInit);

    // Backup: 2 mp múlva mindenképp próbáljuk
    setTimeout(tryInit, 2000);

    function tryInit() {
        if (panelInjected) return;
        if (!document.body) return;
        panelInjected = true;
        injectPanelHTML();
        bindEvents();
        console.log('[AIE] Panel injected.');
    }

    // ── UI injektálás ─────────────────────────────────────────────────────────

    function injectPanelHTML() {
        const panelHTML = `
            <div id="aie-panel" style="
                position: fixed;
                bottom: 20px;
                right: 20px;
                width: 360px;
                background: #1a1a2e;
                border: 1px solid #e94560;
                border-radius: 12px;
                box-shadow: 0 8px 32px rgba(0,0,0,0.5);
                z-index: 99999;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                overflow: hidden;
            ">
                <div style="
                    background: linear-gradient(135deg, #e94560, #0f3460);
                    padding: 14px 18px;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    cursor: move;
                " id="aie-panel-header">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span style="font-size:18px;">🤖</span>
                        <strong style="color:#fff;font-size:14px;">AI Elementor Builder</strong>
                    </div>
                    <button id="aie-toggle" type="button" style="
                        background:none;border:none;color:#fff;cursor:pointer;font-size:18px;
                        line-height:1;padding:0;
                    ">−</button>
                </div>

                <div id="aie-panel-body" style="padding:16px;">
                    <div style="margin-bottom:12px;">
                        <label style="color:#aaa;font-size:11px;text-transform:uppercase;letter-spacing:.5px;">
                            Mód
                        </label>
                        <div style="display:flex;gap:8px;margin-top:6px;">
                            <label style="color:#fff;font-size:13px;cursor:pointer;">
                                <input type="radio" name="aie-mode" value="auto" checked> Auto
                            </label>
                            <label style="color:#fff;font-size:13px;cursor:pointer;">
                                <input type="radio" name="aie-mode" value="create"> Új
                            </label>
                            <label style="color:#fff;font-size:13px;cursor:pointer;">
                                <input type="radio" name="aie-mode" value="modify"> Módosít
                            </label>
                        </div>
                    </div>

                    <textarea id="aie-prompt" placeholder="Pl.: Készíts egy modern hero szekciót kék háttérrel és CTA gombbal..." style="
                        width: 100%;
                        height: 100px;
                        background: #0f0f23;
                        border: 1px solid #333;
                        border-radius: 8px;
                        color: #e0e0e0;
                        font-size: 13px;
                        padding: 10px;
                        resize: vertical;
                        box-sizing: border-box;
                        font-family: inherit;
                    "></textarea>

                    <button id="aie-generate" type="button" style="
                        width: 100%;
                        margin-top: 10px;
                        background: linear-gradient(135deg, #e94560, #c73652);
                        color: #fff;
                        border: none;
                        border-radius: 8px;
                        padding: 12px;
                        font-size: 14px;
                        font-weight: 600;
                        cursor: pointer;
                        transition: opacity .2s;
                    ">
                        ✨ Generálás
                    </button>

                    <div id="aie-status" style="
                        margin-top: 10px;
                        font-size: 12px;
                        color: #aaa;
                        min-height: 20px;
                        word-break: break-word;
                    "></div>
                </div>
            </div>
        `;

        // 1. próba: dedikált mount point (PHP-ban beinjektáltuk)
        const mount = document.getElementById('aie-panel-mount');
        if (mount) {
            mount.innerHTML = panelHTML;
        } else {
            // 2. fallback: egyszerűen body-hoz csapjuk
            $('body').append(panelHTML);
        }

        const panel  = document.getElementById('aie-panel');
        const handle = document.getElementById('aie-panel-header');
        if (panel && handle) {
            makeDraggable(panel, handle);
        }
    }

    // ── Event binding ─────────────────────────────────────────────────────────

    function bindEvents() {
        $(document).on('click', '#aie-toggle', function () {
            const $body = $('#aie-panel-body');
            if ($body.is(':visible')) {
                $body.hide();
                $(this).text('+');
            } else {
                $body.show();
                $(this).text('−');
            }
        });

        $(document).on('click', '#aie-generate', handleGenerate);

        $(document).on('keydown', '#aie-prompt', function (e) {
            if (e.ctrlKey && e.key === 'Enter') {
                handleGenerate();
            }
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

        setStatus('🔄 AI generálás folyamatban (10–30 mp)...', '#3498db');
        setLoading(true);

        $.ajax({
            url:         AIEData.restUrl,
            method:      'POST',
            beforeSend:  function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', AIEData.nonce);
            },
            contentType: 'application/json',
            data:        JSON.stringify({ post_id: postId, prompt: prompt, mode: mode }),
            timeout:     180000, // 3 perc – nagyobb oldalak generálása lassú lehet
            success:     function (response) {
                if (response.success) {
                    setStatus('✅ Kész! Az oldal újratöltésre kerül...', '#27ae60');
                    setTimeout(reloadElementorEditor, 1200);
                } else {
                    setStatus('❌ ' + (response.message || 'Ismeretlen hiba.'), '#e74c3c');
                }
            },
            error: function (xhr) {
                const err = xhr.responseJSON;
                const msg = err ? (err.message || err.code || JSON.stringify(err)) : (xhr.statusText || 'Hálózati hiba');
                setStatus('❌ ' + msg, '#e74c3c');
                console.error('[AIE]', xhr);
            },
            complete: function () {
                setLoading(false);
            },
        });
    }

    // ── Segédfüggvények ───────────────────────────────────────────────────────

    function setStatus(msg, color) {
        $('#aie-status').css('color', color || '#aaa').text(msg);
    }

    function setLoading(isLoading) {
        const $btn = $('#aie-generate');
        $btn.prop('disabled', isLoading).css('opacity', isLoading ? 0.6 : 1);
        $btn.text(isLoading ? '⏳ Feldolgozás...' : '✨ Generálás');
    }

    function getPostIdFromURL() {
        const params = new URLSearchParams(window.location.search);
        return parseInt(params.get('post') || '0', 10) || null;
    }

    function reloadElementorEditor() {
        try {
            if (window.elementor && window.elementor.documents && window.elementor.documents.getCurrent) {
                // Modern Elementor: dokumentum újratöltése preview reload nélkül
                const doc = window.elementor.documents.getCurrent();
                if (doc && doc.refresh) {
                    doc.refresh();
                    return;
                }
            }
        } catch (e) {
            console.warn('[AIE] Soft reload failed, doing full reload.', e);
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

        document.addEventListener('mouseup', function () {
            isDragging = false;
        });
    }

}(jQuery));
