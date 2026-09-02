// Подключаем tag.js сразу (универсально для обоих режимов)
(function (m, e, t, r, i, k, a) {
    m[i] = m[i] || function () { (m[i].a = m[i].a || []).push(arguments); };
    m[i].l = 1 * new Date();
    k = e.createElement(t), a = e.getElementsByTagName(t)[0];
    k.async = 1;
    k.src = r;
    a.parentNode.insertBefore(k, a);
})(window, document, 'script', '//mc.yandex.ru/metrika/tag.js', 'ym');

(function ($, Drupal) {
    // Флаг, чтобы не инициализировать Метрику несколько раз
    var vpaCounterInitialized = false;

    Drupal.behaviors.vesta_privacy_access = {
        attach: function (context) {
            if (context !== document) return;

            var s = Drupal.settings || drupalSettings;
            var conf = s.vesta_privacy_access || {};
            var enabled = !!conf.ya_metrika_enable;
            var id = conf.id_ya_metrika;
            var mode = conf.mode || 'short';

            if (!enabled) return;
            if (!id) {
                console.warn('Счётчик Яндекс Метрики не задан.');
                return;
            }

            // Утилиты
            function addClass(o, c) {
                if (o && !o.classList.contains(c)) o.classList.add(c);
            }

            function removeClass(o, c) {
                if (o) o.classList.remove(c);
            }

            function show(el) {
                if (!el) return;
                // 1) вернуть элемент в поток (убрать display:none)
                removeClass(el, 'vpa-hidden');
                // 2) принудительный reflow, чтобы браузер «увидел» стартовые стили
                void el.getBoundingClientRect();
                // 3) целевое состояние — запустится переход translateY/opacity
                addClass(el, 'vpa-visible');
            }

            function hide(el) {
                if (!el) return;
                // Уберём видимость (поедет вниз обратно)
                removeClass(el, 'vpa-visible');

                // После окончания transition снова выключим из потока
                var onEnd = function (e) {
                    if (e.propertyName === 'transform' || e.propertyName === 'opacity') {
                        addClass(el, 'vpa-hidden');
                        el.removeEventListener('transitionend', onEnd);
                    }
                };
                el.addEventListener('transitionend', onEnd, { once: true });

                // На всякий случай таймаут-страховка (если transitionend не сработает)
                setTimeout(function () {
                    if (!el.classList.contains('vpa-visible')) addClass(el, 'vpa-hidden');
                }, 1000);
            }

            function initCounter() {
                if (vpaCounterInitialized) return;
                if (!window.ym || !id) return;
                ym(parseInt(id, 10), 'init', {
                    clickmap: true,
                    trackLinks: true,
                    accurateTrackBounce: true,
                    webvisor: true,
                    trackHash: true,
                    ecommerce: "dataLayer"
                });
                vpaCounterInitialized = true;
            }

            // Свайп вниз по "ручке" для закрытия (мобилки)
            function enableSwipeToClose(wrapper, onAccept) {
                if (!wrapper) return;
            
                var isMobile = window.matchMedia && window.matchMedia('(max-width: 767px)').matches;
                if (!isMobile) return;
            
                var zone = wrapper.querySelector('.vpa-close-zone');
                if (!zone) return;
            
                var startY = 0;
                var currentY = 0;
                var startX = 0;
                var currentX = 0;
                var startTime = 0;
            
                var dragging = false;
                var moved = false;
                var closing = false;
            
                var minSwipe = 80;       // насколько надо протянуть вниз
                var maxHorizontal = 40;  // допустимое отклонение по X
                var maxTime = 1000;      // максимальная длительность жеста
            
                zone.style.touchAction = 'none';
            
                function setDragOffset(offset) {
                    if (offset < 0) offset = 0;
                    wrapper.style.transform = 'translateY(' + offset + 'px)';
                    wrapper.style.opacity = String(Math.max(0.4, 1 - offset / 300));
                }
            
                function resetPosition() {
                    wrapper.style.transition = 'transform 220ms ease, opacity 220ms ease';
                    wrapper.style.transform = '';
                    wrapper.style.opacity = '';
                    setTimeout(function () {
                        wrapper.style.transition = '';
                    }, 240);
                }
            
                function finishClose() {
                    if (closing) return;
                    closing = true;
                
                    wrapper.style.transition = 'transform 180ms ease, opacity 180ms ease';
                    wrapper.style.transform = 'translateY(100%)';
                    wrapper.style.opacity = '0';
                
                    setTimeout(function () {
                        wrapper.style.transition = '';
                        wrapper.style.transform = '';
                        wrapper.style.opacity = '';
                        onAccept && onAccept();
                        closing = false;
                    }, 180);
                }
            
                zone.addEventListener('touchstart', function (e) {
                    if (!e.touches || !e.touches.length || closing) return;
            
                    var t = e.touches[0];
                    startY = currentY = t.clientY;
                    startX = currentX = t.clientX;
                    startTime = Date.now();
                    dragging = true;
                    moved = false;
            
                    wrapper.style.transition = 'none';
                }, { passive: true });
            
                zone.addEventListener('touchmove', function (e) {
                    if (!dragging || !e.touches || !e.touches.length || closing) return;
            
                    var t = e.touches[0];
                    currentY = t.clientY;
                    currentX = t.clientX;
            
                    var dy = currentY - startY;
                    var dx = Math.abs(currentX - startX);
            
                    if (Math.abs(dy) > 3 || dx > 3) {
                        moved = true;
                    }
            
                    if (dy > 0 && dx < maxHorizontal) {
                        e.preventDefault();
            
                        // легкое сопротивление, чтобы движение было приятнее
                        var offset = dy * 0.95;
                        setDragOffset(offset);
                    }
                }, { passive: false });
            
                zone.addEventListener('touchend', function () {
                    if (!dragging || closing) return;
            
                    var dy = currentY - startY;
                    var dx = Math.abs(currentX - startX);
                    var dt = Date.now() - startTime;
            
                    dragging = false;
            
                    if (moved && dy > minSwipe && dx < maxHorizontal && dt < maxTime) {
                        finishClose();
                    } else {
                        resetPosition();
                    }
                }, { passive: true });
            
                zone.addEventListener('touchcancel', function () {
                    if (!dragging || closing) return;
                    dragging = false;
                    resetPosition();
                }, { passive: true });
            
            
                // Мышь для десктопа
                var mDown = false;
                var mStartY = 0;
                var mCurrentY = 0;
                var mStartX = 0;
                var mCurrentX = 0;
                var mStartTime = 0;
                var mMoved = false;
            
                zone.addEventListener('mousedown', function (e) {
                    if (closing) return;
            
                    mDown = true;
                    mMoved = false;
                    mStartY = mCurrentY = e.clientY;
                    mStartX = mCurrentX = e.clientX;
                    mStartTime = Date.now();
            
                    wrapper.style.transition = 'none';
            
                    e.preventDefault();
                });
            
                window.addEventListener('mousemove', function (e) {
                    if (!mDown || closing) return;
            
                    mCurrentY = e.clientY;
                    mCurrentX = e.clientX;
            
                    var dy = mCurrentY - mStartY;
                    var dx = Math.abs(mCurrentX - mStartX);
            
                    if (Math.abs(dy) > 3 || dx > 3) {
                        mMoved = true;
                    }
            
                    if (dy > 0 && dx < maxHorizontal) {
                        setDragOffset(dy * 0.95);
                    }
                });
            
                window.addEventListener('mouseup', function () {
                    if (!mDown || closing) return;
            
                    var dy = mCurrentY - mStartY;
                    var dx = Math.abs(mCurrentX - mStartX);
                    var dt = Date.now() - mStartTime;
            
                    mDown = false;
            
                    if (mMoved && dy > minSwipe && dx < maxHorizontal && dt < maxTime) {
                        finishClose();
                    } else {
                        resetPosition();
                    }
                });
            }

            // Разные сценарии
            if (mode === 'short') {
                var wrapper = document.getElementById('vpa-short-wrapper');
                if (!wrapper) return;

                // Метрика включается ВСЕГДА сразу
                initCounter();

                // Показать уведомление, если пользователь ещё не нажимал «Хорошо»
                if (!Cookies.get('vpa_short_ok')) {
                    show(wrapper);
                }

                // Кнопка «Хорошо»
                var ok = document.getElementById('vpa-btn-ok');
                if (ok) {
                    ok.addEventListener('click', function () {
                        Cookies.set('vpa_short_ok', '1', { expires: 365 });
                        hide(wrapper);
                    });
                }

                // Свайп вниз = принять и скрыть (метрика уже включена)
                enableSwipeToClose(wrapper, function () {
                    Cookies.set('vpa_short_ok', '1', { expires: 365 });
                    hide(wrapper);
                });
            }
            else { // FULL
                var wrapperF = document.getElementById('vpa-full-wrapper');
                if (!wrapperF) return;

                // Если ранее согласился — уже инициализируем без показа окна
                if (Cookies.get('vpa_full_agree')) {
                    initCounter();
                } else if (!Cookies.get('vpa_full_reject')) {
                    show(wrapperF);
                }

                // Кнопки
                var yes = document.getElementById('vpa-btn-yes');
                var no = document.getElementById('vpa-btn-no');

                if (yes) {
                    yes.addEventListener('click', function () {
                        Cookies.set('vpa_full_agree', '1', { expires: 365 });
                        initCounter();
                        hide(wrapperF);
                    });
                }

                if (no) {
                    no.addEventListener('click', function () {
                        Cookies.set('vpa_full_reject', '1', { expires: 365 });
                        hide(wrapperF);
                    });
                }

                // Свайп вниз = согласие (как «Разрешаю»)
                enableSwipeToClose(wrapperF, function () {
                    Cookies.set('vpa_full_agree', '1', { expires: 365 });
                    initCounter();
                    hide(wrapperF);
                });

            }
        }
    };
})(jQuery, Drupal);
