/**
 * AI Elementor Builder – Editor Panel
 * Elementor szerkesztőbe injektált AI panel.
 */
(function ($) {
    'use strict';

    // Várjuk meg, amíg az Elementor editor betölt
    $(window).on('elementor:init', function () {
        initAIPanel();
    });

    // Fallback: ha az event már lefutott
    if (window.elementor) {
        initAIPanel();
    }

    function initAIPanel() {
        // Panel injektálása az Elementor toolbar-ba
        injectPanelHTML();
        bindEvents();
    }

    // ── UI injektálás ─────────────────────────────────────────────────────────

    function injectPanelHTML() {
        const panelHTML = `
            <div id="aie-panel" style="
                position: fixed;
                bottom: 80px;
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
                <!-- Header -->
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
                    <button id="aie-toggle" style="
                        background:none;border:none;color:#fff;cursor:pointer;font-size:18px;
                        line-height:1;padding:0;
                    ">−</button>
                </div>

                <!-- Body -->
                <div id="aie-panel-body" style="padding:16px;">
                    <!-- Mode selector -->
                    <div style="margin-bottom:12px;">
                        <label style="color:#aaa;font-size:11px;text-transform:uppercase;letter-spacing:.5px;">
                            Mód
                        </label>
                        <div style="display:flex;gap:8px;margin-top:6px;">
                            <label style="color:#fff;font-size:13px;cursor:pointer;">
                                <input type="radio" name="aie-mode" value="auto" checked> Auto
                            </label>
                            <label style="color:#fff;font-size:13px;cursor:pointer;">
                                <input type="radio" name="aie-mode" value="create"> Új oldal
                            </label>
                            <label style="color:#fff;font-size:13px;cursor:pointer;">
                                <input type="radio" name="aie-mode" value="modify"> Módosítás
                            </label>
                        </div>
                    </div>

                    <!-- Prompt textarea -->
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

                    <!-- Generate button -->
                    <button id="aie-generate" style="
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

                    <!-- Status / log -->
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

        $('body').append(panelHTML);

        // Draggable header (egyszerű implementáció)
        makeDraggable(document.getElementById('aie-panel'), document.getElementById('aie-panel-header'));
    }

    // ── Event binding ─────────────────────────────────────────────────────────

    function bindEvents() {
        // Toggle collapse
        $('#aie-toggle').on('click', function () {
            const $body = $('#aie-panel-body');
            if ($body.is(':visible')) {
                $body.hide();
                $(this).text('+');
            } else {
                $body.show();
                $(this).text('−');
            }
        });

        // Generate gomb
        $('#aie-generate').on('click', handleGenerate);

        // Enter → Ctrl+Enter küld
        $('#aie-prompt').on('keydown', function (e) {
            if (e.ctrlKey && e.key === 'Enter') {
                handleGenerate();
            }
        });
    }

    // ── AI generálás ──────────────────────────────────────────────────────────

    function handleGenerate() {
        const prompt  = $('#aie-prompt').val().trim();
        const mode    = $('input[name="aie-mode"]:checked').val();
        const postId  = AIEData.postId || getPostIdFromURL();

        if (!prompt) {
            setStatus('⚠️ Kérlek írj be egy promptot!', '#f39c12');
            return;
        }

        if (!postId) {
            setStatus('❌ Nem található az oldal ID. Mentsd el az oldalt először!', '#e74c3c');
            return;
        }

        setStatus('🔄 AI generálás folyamatban...', '#3498db');
        setLoading(true);

        $.ajax({
            url:         AIEData.restUrl,
            method:      'POST',
            beforeSend:  function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', AIEData.nonce);
            },
            contentType: 'application/json',
            data:        JSON.stringify({ post_id: postId, prompt, mode }),
            success:     function (response) {
                if (response.success) {
                    setStatus('✅ Kész! Az oldal újratöltésre kerül...', '#27ae60');
                    // Elementor reload – frissíti a szerkesztőt az új adatokkal
                    setTimeout(function () {
                        reloadElementorEditor();
                    }, 1200);
                } else {
                    setStatus('❌ Hiba: ' + (response.message || 'Ismeretlen hiba.'), '#e74c3c');
                }
            },
            error:        function (xhr) {
                const err = xhr.responseJSON;
                const msg = err ? (err.message || err.code || JSON.stringify(err)) : xhr.statusText;
                setStatus('❌ ' + msg, '#e74c3c');
            },
            complete:     function () {
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
        if (window.elementor && window.elementor.reloadPreview) {
            window.elementor.reloadPreview();
        } else {
            window.location.reload();
        }
    }

    // ── Draggable ─────────────────────────────────────────────────────────────

    function makeDraggable(panel, handle) {
        let offsetX = 0, offsetY = 0, isDragging = false;

        handle.addEventListener('mousedown', function (e) {
            if (e.target.id === 'aie-toggle') return;
            isDragging = true;
            offsetX = e.clientX - panel.getBoundingClientRect().left;
            offsetY = e.clientY - panel.getBoundingClientRect().top;
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
