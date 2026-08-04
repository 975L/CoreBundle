/*
 * (c) 2024: 975L <contact@975l.com>
 * (c) 2024: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";
import { createNoncedStyleElement } from "./nonced-style-element.js";

// --slider-freeflow-vw is a page-wide value (drives every freeflow slider's full-bleed breakout width, not just this instance's own), so it's kept in one shared <style> element for the whole page's lifetime instead of a per-controller one - unlike the per-slider height rule below, which is created and torn down with its own instance's connect()/disconnect().
let sharedFreeflowStyleEl = null;

export default class extends Controller {
    connect() {
        const sliderId = this.element.dataset.sliderId;
        if (sliderId) {
            this.slideIndex = 1;
            this.isPlaying = false;
            this.isFreeflow = this.element.classList.contains("slider-freeflow");
            // Slide videos auto-play muted, like an animated image - but not for users who asked the OS to reduce motion. Native <video controls> stays available either way so they can still start it manually.
            this.reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
            this.createLiveRegion(sliderId);
            this.preloadSliderImages(sliderId);
            this.initializeSlider(sliderId);
            this.resizeSlider(sliderId);
            this.setupAccessibility(sliderId);
            this.setupTouchGestures(sliderId);
            this.startAutoPlay(sliderId);
        }
    }

    disconnect() {
        if (this.autoPlayInterval) {
            clearInterval(this.autoPlayInterval);
        }
        clearTimeout(this.deactivateTimeout);
        this.heightStyleEl?.remove();
    }

    // Create ARIA live region for announcements
    createLiveRegion(sliderId) {
        const carousel = document.querySelector(`#${sliderId}`);
        let liveRegion = carousel.querySelector(".slider-liveregion");

        if (!liveRegion) {
            liveRegion = document.createElement("div");
            liveRegion.setAttribute("aria-live", "polite");
            liveRegion.setAttribute("aria-atomic", "true");
            liveRegion.className = "slider-liveregion visuallyhidden";
            carousel.appendChild(liveRegion);
        }
    }

    // Announce slide change to screen readers
    announceSlide(sliderId, current, total) {
        const liveRegion = document.querySelector(`#${sliderId} .slider-liveregion`);
        if (liveRegion) {
            liveRegion.textContent = `Item ${current} of ${total}`;
        }
    }

    // preloadSliderImages
    preloadSliderImages(sliderId) {
        const slides = document.querySelectorAll(`#${sliderId} .slider-item img`);

        slides.forEach((img, index) => {
            if (index === 0) {
                img.loading = "eager";
            } else {
                const preloadImg = new Image();
                preloadImg.src = img.src;
                preloadImg.onload = () => {
                    img.loading = "eager";
                    img.classList.add("preloaded");
                };
            }
        });
    }

    // setupAccessibility
    setupAccessibility(sliderId) {
        const carousel = document.querySelector(`#${sliderId}`);

        // Pause on mouse hover
        carousel.addEventListener("mouseenter", () => this.suspendAnimation());
        carousel.addEventListener("mouseleave", () => this.resumeAnimation(sliderId));

        // Pause on keyboard focus
        carousel.addEventListener("focusin", (e) => {
            if (!e.target.classList.contains("slider-item")) {
                this.suspendAnimation();
            }
        });
        carousel.addEventListener("focusout", (e) => {
            if (!e.target.classList.contains("slider-item")) {
                this.resumeAnimation(sliderId);
            }
        });

        // Play/Pause button
        const playPauseBtn = carousel.querySelector(".slider-play-pause");
        if (playPauseBtn) {
            playPauseBtn.addEventListener("click", () => this.togglePlayPause(sliderId, playPauseBtn));
        }
    }

    // Touch gestures: swipe left/right to navigate, press-and-hold to pause. A plain tap (released quickly, without moving) falls through to the existing "click on slide" listener set up in initializeSlider(), which advances to the next slide.
    setupTouchGestures(sliderId) {
        // Freeflow scrolls its own list rather than swapping slides, so it drives that scroll itself
        if (this.isFreeflow) {
            this.setupFreeflowGestures(sliderId);
            return;
        }

        const longPressDelay = 500;
        const swipeThreshold = 50;
        const items = document.querySelectorAll(`#${sliderId} .slider-item`);

        items.forEach((item) => {
            let startX = 0;
            let startY = 0;
            let longPressTimer = null;
            let isLongPress = false;
            let isSwipe = false;

            item.addEventListener("touchstart", (e) => {
                const touch = e.touches[0];
                startX = touch.clientX;
                startY = touch.clientY;
                isLongPress = false;
                isSwipe = false;

                longPressTimer = setTimeout(() => {
                    isLongPress = true;
                    this.suspendAnimation();
                }, longPressDelay);
            }, { passive: true });

            item.addEventListener("touchmove", (e) => {
                const touch = e.touches[0];
                const deltaX = touch.clientX - startX;
                const deltaY = touch.clientY - startY;

                if (!isSwipe && Math.abs(deltaX) > swipeThreshold && Math.abs(deltaX) > Math.abs(deltaY)) {
                    isSwipe = true;
                    clearTimeout(longPressTimer);
                }

                if (isSwipe) {
                    e.preventDefault();
                }
            }, { passive: false });

            item.addEventListener("touchend", (e) => {
                clearTimeout(longPressTimer);

                if (isSwipe) {
                    e.preventDefault();
                    // A touchend is no longer cancelable once the browser has begun its own gesture, so preventDefault() alone doesn't always suppress the compatibility click that follows - it would advance a second slide on top of the swipe's own
                    this.swipedAt = Date.now();
                    const deltaX = e.changedTouches[0].clientX - startX;
                    if (deltaX < 0) {
                        this.displaySlide(sliderId, ++this.slideIndex, "next");
                    } else {
                        this.displaySlide(sliderId, --this.slideIndex, "prev");
                    }
                } else if (isLongPress) {
                    e.preventDefault();
                    this.resumeAnimation(sliderId);
                }
            }, { passive: false });

            item.addEventListener("touchcancel", () => {
                clearTimeout(longPressTimer);
                if (isLongPress) {
                    this.resumeAnimation(sliderId);
                }
            });
        });
    }

    // Freeflow gestures: the browser's own fling would carry several slides past the one asked for and its dots would never follow, so the horizontal drag is taken over here - the list is scrolled by hand while the finger moves, then snapped to exactly one slide either way. Vertical panning is left to the browser (touch-action: pan-y).
    setupFreeflowGestures(sliderId) {
        const list = document.querySelector(`#${sliderId} .slider-list`);
        if (!list) {
            return;
        }

        const swipeThreshold = 40;
        let startX = 0;
        let startY = 0;
        let startScroll = 0;
        let startIndex = 1;
        let decided = false;
        let isSwipe = false;

        list.addEventListener("touchstart", (e) => {
            const touch = e.touches[0];
            startX = touch.clientX;
            startY = touch.clientY;
            startScroll = list.scrollLeft;
            // The slide the gesture started on: the dots follow the finger as it drags, moving slideIndex along with them, so a "one slide further" read off it on release would count that drag twice
            startIndex = this.slideIndex;
            decided = false;
            isSwipe = false;
            this.suspendAnimation();
        }, { passive: true });

        list.addEventListener("touchmove", (e) => {
            const touch = e.touches[0];
            const deltaX = touch.clientX - startX;
            const deltaY = touch.clientY - startY;

            if (!decided && (Math.abs(deltaX) > 10 || Math.abs(deltaY) > 10)) {
                decided = true;
                isSwipe = Math.abs(deltaX) > Math.abs(deltaY);
                // Snapping and smooth scrolling both fight a per-frame scrollLeft, so they are off for the duration of the drag only
                if (isSwipe) {
                    list.classList.add("slider-dragging");
                }
            }

            if (isSwipe) {
                e.preventDefault();
                list.scrollLeft = startScroll - deltaX;
            }
        }, { passive: false });

        const endGesture = (e) => {
            if (isSwipe) {
                const slides = document.querySelectorAll(`#${sliderId} .slider-item`);
                const deltaX = (e.changedTouches?.[0]?.clientX ?? startX) - startX;
                const step = Math.abs(deltaX) > swipeThreshold ? (deltaX < 0 ? 1 : -1) : 0;
                list.classList.remove("slider-dragging");
                // Clamped rather than wrapped: a drag is already stopped by the list's own ends, jumping to the far side would contradict what the finger just did
                const target = Math.min(Math.max(startIndex + step, 1), slides.length);
                this.displaySlideFreeflow(sliderId, target);
            }
            this.resumeAnimation(sliderId);
        };

        list.addEventListener("touchend", endGesture, { passive: true });
        list.addEventListener("touchcancel", endGesture, { passive: true });

        // Any other way of scrolling the list - trackpad, mouse wheel, a dragged scrollbar, the finger above - still has to move the dots along. Throttled to one frame, and only acted on when the centered slide actually changed.
        let scrollFrame = null;
        list.addEventListener("scroll", () => {
            if (scrollFrame) {
                return;
            }
            scrollFrame = requestAnimationFrame(() => {
                scrollFrame = null;
                const slides = document.querySelectorAll(`#${sliderId} .slider-item`);
                const index = this.centeredSlideIndex(list, slides);
                if (index !== this.slideIndex) {
                    this.syncFreeflowState(sliderId, index, slides, true);
                }
            });
        }, { passive: true });
    }

    // Index (1-based) of the slide whose center is nearest the list's own center - what "the current slide" means once the list is scrolled by hand
    centeredSlideIndex(list, slides) {
        const listCenter = list.getBoundingClientRect().left + list.clientWidth / 2;

        return slides.length === 0 ? 1 : Array.from(slides).reduce((best, slide, idx) => {
            const rect = slide.getBoundingClientRect();
            const distance = Math.abs(rect.left + rect.width / 2 - listCenter);
            return distance < best.distance ? { index: idx + 1, distance } : best;
        }, { index: 1, distance: Infinity }).index;
    }

    // Plays the <video> inside a slide, if any. Suppressed under prefers-reduced-motion unless explicit is true (user pressed the play/pause control themselves, which overrides it).
    playSlideVideo(slide, explicit = false) {
        const video = slide?.querySelector("video");
        if (video && (explicit || !this.reducedMotion)) {
            video.play().catch(() => {});
        }
    }

    // Pauses the <video> inside a slide, if any
    pauseSlideVideo(slide) {
        const video = slide?.querySelector("video");
        if (video) {
            video.pause();
        }
    }

    suspendAnimation() {
        if (this.autoPlayInterval) {
            clearInterval(this.autoPlayInterval);
            this.autoPlayInterval = null;
        }
    }

    resumeAnimation(sliderId) {
        if (this.isPlaying && !this.autoPlayInterval) {
            this.startAutoPlay(sliderId);
        }
    }

    togglePlayPause(sliderId, button) {
        const action = button.getAttribute("data-action");
        const activeSlide = Array.from(document.querySelectorAll(`#${sliderId} .slider-item`))
            .find((slide) => slide.classList.contains("slider-item-active"));

        if (action === "stop") {
            this.suspendAnimation();
            this.isPlaying = false;
            this.pauseSlideVideo(activeSlide);
            button.setAttribute("data-action", "start");
            button.setAttribute("aria-label", button.getAttribute("aria-label").replace("Stop", "Start").replace("Arrêter", "Démarrer").replace("Detener", "Iniciar"));
            button.innerHTML = '<span aria-hidden="true">▶</span>';
        } else {
            this.isPlaying = true;
            this.startAutoPlay(sliderId);
            // Explicit user action: play the active slide's video even under prefers-reduced-motion
            this.playSlideVideo(activeSlide, true);
            button.setAttribute("data-action", "stop");
            button.setAttribute("aria-label", button.getAttribute("aria-label").replace("Start", "Stop").replace("Démarrer", "Arrêter").replace("Iniciar", "Detener"));
            button.innerHTML = '<span aria-hidden="true">⏸</span>';
        }
    }

    // initializeSlider
    initializeSlider(sliderId) {
        const prevBtn = document.querySelector(`#${sliderId} .slider-prev`);
        const nextBtn = document.querySelector(`#${sliderId} .slider-next`);

        if (!prevBtn || !nextBtn) {
            return;
        }

        // Display the first slide
        this.displaySlide(sliderId, this.slideIndex, "none");

        // Previous slide
        prevBtn.addEventListener("click", () => {
            this.displaySlide(sliderId, --this.slideIndex, "prev");
        });

        // Next slide
        nextBtn.addEventListener("click", () => {
            this.displaySlide(sliderId, ++this.slideIndex, "next");
        });

        // Click on slide to go next, unless the click is on a link (title/text/image linking to slide.url) or on a video's native controls (play/pause/volume/seek)
        const slides = document.querySelectorAll(`#${sliderId} .slider-item`);
        slides.forEach((slide) => {
            slide.addEventListener("click", (e) => {
                if (e.target.closest("a") || e.target.tagName === "VIDEO" || Date.now() - (this.swipedAt || 0) < 500) {
                    return;
                }
                this.displaySlide(sliderId, ++this.slideIndex, "next");
            });
        });

        // Navigation dots
        const dots = document.querySelectorAll(`#${sliderId} .slider-dot`);
        dots.forEach((dot) => {
            dot.addEventListener("click", () => {
                const targetSlide = parseInt(dot.getAttribute("data-slide"), 10) + 1;
                const direction = targetSlide > this.slideIndex ? "next" : "prev";
                this.displaySlide(sliderId, targetSlide, direction);
            });
        });
    }

    // resizeSlider
    resizeSlider(sliderId) {
        const slider = document.querySelector(`#${sliderId}`);
        const slides = document.querySelectorAll(`#${sliderId} .slider-item`);

        if (slides.length === 0) {
            return;
        }

        // Freeflow slides stay in normal flow (natural height) - only the --slider-freeflow-vw custom property (drives the full-bleed breakout width; computed via clientWidth rather than raw vw units so a desktop scrollbar's own width can't create a horizontal scroll) needs refreshing when the viewport resizes; re-scrolling the current slide into place keeps it aligned after the row's item widths (clamp(), viewport-based) change.
        if (this.isFreeflow) {
            const updateFreeflow = () => {
                sharedFreeflowStyleEl ??= createNoncedStyleElement();
                sharedFreeflowStyleEl.textContent = `:root { --slider-freeflow-vw: ${document.documentElement.clientWidth}px; }`;
                this.displaySlideFreeflow(sliderId, this.slideIndex, false);
            };
            updateFreeflow();
            let freeflowResizeTimeout;
            window.addEventListener("resize", () => {
                clearTimeout(freeflowResizeTimeout);
                freeflowResizeTimeout = setTimeout(updateFreeflow, 300);
            });
            return;
        }

        // A fixed ratio (CSS aspect-ratio, via a "slider-ratio-*" class) already sizes the slider. This JS height fallback is only needed in free/natural ratio mode, where slides are absolutely positioned and stacked, so the container can't size itself from content alone.
        const hasFixedRatio = Array.from(slider.classList).some((c) => c.startsWith("slider-ratio-"));
        if (hasFixedRatio) {
            return;
        }

        const mediaEls = Array.from(slides)
            .map((slide) => slide.querySelector("img, video"))
            .filter((el) => el !== null);

        // Sets the slider height to the tallest media scaled to the slider's width, using the width/height HTML attributes (Media entity dimensions, images only) which are known synchronously - no waiting on load events, which used to grow the slider after the fact and shift bottom-anchored text/credits down. Videos never have stored dimensions (see MediaUploadType), so their natural size is only known once "loadedmetadata" fires below. "slider-sized" then lets every slide - including smaller media - fill that height and get cropped via object-fit: cover.
        const applyMaxHeight = () => {
            const width = slider.clientWidth;
            const maxHeight = mediaEls.reduce((max, el) => {
                const isVideo = el.tagName === "VIDEO";
                const naturalWidth = isVideo ? el.videoWidth : (el.naturalWidth || el.width);
                const naturalHeight = isVideo ? el.videoHeight : (el.naturalHeight || el.height);
                if (!naturalWidth || !naturalHeight) {
                    return max;
                }
                return Math.max(max, naturalHeight * (width / naturalWidth));
            }, 0);

            if (maxHeight > 0) {
                this.heightStyleEl ??= createNoncedStyleElement();
                this.heightStyleEl.textContent = `#${CSS.escape(sliderId)} { height: ${maxHeight}px; }`;
                slider.classList.add("slider-sized");
            }
        };

        applyMaxHeight();

        // Fallback: refine once the real image loads, or once a video's metadata is known
        mediaEls.forEach((el) => {
            if (el.tagName === "VIDEO") {
                if (!el.videoWidth || !el.videoHeight) {
                    el.addEventListener("loadedmetadata", applyMaxHeight, { once: true });
                }
            } else if (!el.getAttribute("width") || !el.getAttribute("height")) {
                el.addEventListener("load", applyMaxHeight, { once: true });
            }
        });

        // Recalculates height in case of resizing the window, waits that resize is finished to avoid multiple calculations
        let resizeTimeout;
        window.addEventListener("resize", () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(applyMaxHeight, 300);
        });
    }

    // Display slide with ARIA support
    displaySlide(sliderId, number, direction = "next", announceChange = true) {
        if (this.isFreeflow) {
            this.displaySlideFreeflow(sliderId, number, announceChange);
            return;
        }

        const slides = document.querySelectorAll(`#${sliderId} .slider-item`);
        const dots = document.querySelectorAll(`#${sliderId} .slider-dot`);

        if (slides.length === 0) {
            return;
        }

        // Calculate correct index
        const index = this.calculateIndex(number, slides.length);

        // Current slide read from the tracked index, not from the first one still classed active: the outgoing slide only loses that class 500ms later, so a second swipe landing mid-animation used to pick the slide before last and leave the one in between displayed for good, dots pointing elsewhere
        const currentSlide = slides[this.slideIndex - 1];
        const newSlide = slides[index - 1];

        // Whatever a previous, still unfinished transition left behind - only the outgoing and incoming slides take part in this one
        clearTimeout(this.deactivateTimeout);
        slides.forEach((slide) => {
            if (slide !== currentSlide && slide !== newSlide) {
                slide.classList.remove("slider-item-active");
            }
        });

        // Remove animation classes
        slides.forEach((slide) => {
            slide.classList.remove("slide-in-right", "slide-in-left", "slide-out-right", "slide-out-left");
        });

        // Manage ARIA attributes
        slides.forEach((slide, idx) => {
            if (idx === index - 1) {
                slide.removeAttribute("aria-hidden");
            } else {
                slide.setAttribute("aria-hidden", "true");
            }
        });

        // Add animations if not initial display
        if (currentSlide && currentSlide !== newSlide && direction !== "none") {
            if (direction === "next") {
                currentSlide.classList.add("slide-out-left");
                newSlide.classList.add("slide-in-right");
            } else {
                currentSlide.classList.add("slide-out-right");
                newSlide.classList.add("slide-in-left");
            }

            this.deactivateTimeout = setTimeout(() => {
                currentSlide.classList.remove("slider-item-active");
            }, 500);
        } else if (currentSlide && currentSlide !== newSlide) {
            currentSlide.classList.remove("slider-item-active");
        }

        // Update dots
        dots.forEach((dot, idx) => {
            if (idx === index - 1) {
                dot.classList.add("current", "active");
                dot.setAttribute("aria-label", `${dot.getAttribute("aria-label").replace(/\(.*?\)/, "")} (current)`);
            } else {
                dot.classList.remove("current", "active");
                dot.setAttribute("aria-label", dot.getAttribute("aria-label").replace(/\s*\(.*?\)/, ""));
            }
        });

        // Display new slide
        newSlide.classList.add("slider-item-active");

        // A slide's video only plays while its slide is the active one
        if (currentSlide && currentSlide !== newSlide) {
            this.pauseSlideVideo(currentSlide);
        }
        this.playSlideVideo(newSlide);

        // Announce change
        if (announceChange && direction !== "none") {
            this.announceSlide(sliderId, index, slides.length);
        }

        // Update state
        this.slideIndex = index;
    }

    // Freeflow layout: every slide stays visible side by side (no display none/block toggling), .slider-list scrolls natively (overflow-x: auto + scroll-snap, see _slider.scss) - this just drives that scroll and updates dots/video state, like the default slider's displaySlide
    displaySlideFreeflow(sliderId, number, announceChange = true) {
        const list = document.querySelector(`#${sliderId} .slider-list`);
        const slides = document.querySelectorAll(`#${sliderId} .slider-item`);

        if (slides.length === 0) {
            return;
        }

        const index = this.calculateIndex(number, slides.length);
        const newSlide = slides[index - 1];

        // Centers the slide in the list, matching scroll-snap-align: center on .slider-item, and scrolls that list alone. Deliberately not newSlide.scrollIntoView(): its "block" axis climbs ancestor scrollers, including the page itself, and yanks the page back to the slider whenever autoplay changes the slide while the user has scrolled away from it.
        if (list) {
            const listRect = list.getBoundingClientRect();
            const slideRect = newSlide.getBoundingClientRect();
            const delta = (slideRect.left + slideRect.width / 2) - (listRect.left + list.clientWidth / 2);
            list.scrollTo({ left: list.scrollLeft + delta, behavior: "smooth" });
        }

        this.syncFreeflowState(sliderId, index, slides, announceChange);
    }

    // Everything a freeflow slide change carries apart from the scroll itself - split out so a scroll driven by the user (swipe, wheel, trackpad) updates the same state without being scrolled back on top of it
    syncFreeflowState(sliderId, index, slides, announceChange = true) {
        const dots = document.querySelectorAll(`#${sliderId} .slider-dot`);
        const currentSlide = slides[this.slideIndex - 1];
        const newSlide = slides[index - 1];

        dots.forEach((dot, idx) => {
            if (idx === index - 1) {
                dot.classList.add("current", "active");
                dot.setAttribute("aria-label", `${dot.getAttribute("aria-label").replace(/\(.*?\)/, "")} (current)`);
            } else {
                dot.classList.remove("current", "active");
                dot.setAttribute("aria-label", dot.getAttribute("aria-label").replace(/\s*\(.*?\)/, ""));
            }
        });

        if (currentSlide !== newSlide) {
            this.pauseSlideVideo(currentSlide);
        }
        this.playSlideVideo(newSlide);

        if (announceChange) {
            this.announceSlide(sliderId, index, slides.length);
        }

        this.slideIndex = index;
    }

    // Helper method to calculate valid index
    calculateIndex(number, length) {
        if (number > length) return 1;
        if (number < 1) return length;
        return number;
    }

    // Auto-play
    startAutoPlay(sliderId) {
        const duration = parseInt(this.element.dataset.sliderDuration, 10);

        if (!duration || duration <= 0) {
            return;
        }

        this.isPlaying = true;

        this.autoPlayInterval = setInterval(() => {
            this.slideIndex++;
            this.displaySlide(sliderId, this.slideIndex, "next", true);
        }, duration);
    }
}
