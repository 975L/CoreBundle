/*
 * (c) 2024: 975L <contact@975l.com>
 * (c) 2024: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    connect() {
        this.animateOnScroll = this.animateOnScroll.bind(this);

        // Applied here rather than server-rendered: if this script never loads (blocked, network error...), ".scroll" elements must never get hidden in the first place - they stay visible by default, just without the entrance effect, instead of being stuck invisible.
        document.querySelectorAll(".scroll").forEach((element) => element.classList.add("hidden"));

        window.addEventListener("scroll", this.animateOnScroll);
        this.animateOnScroll();
    }

    disconnect() {
        window.removeEventListener("scroll", this.animateOnScroll);
    }

    // Checks if element is in viewport
    isElementInViewport(element, offset) {
        if (null !== element) {
            const rect = element.getBoundingClientRect();
            return (
                rect.top < (window.innerHeight || document.documentElement.clientHeight) - offset &&
                rect.bottom >= 0
            );
        }
        return false;
    }

    // Animates on scroll
    animateOnScroll() {
        var elements = document.querySelectorAll(".scroll");
        elements.forEach((element) => {
            if (this.isElementInViewport(element, 200)) {
                const animationClass = element.getAttribute("data-animation");
                if (animationClass) {
                    element.classList.remove("hidden");
                    element.classList.add(animationClass);
                }
            }
        })
    }
}