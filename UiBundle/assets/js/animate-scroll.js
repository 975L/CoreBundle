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
        // An observer rather than a scroll listener reading every element's box, so no layout is forced and an element in view is never hidden first. The bottom margin keeps the former 200px threshold
        this.observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                const element = entry.target.closest(".scroll");

                // Hidden here rather than server-rendered: if this script never loads, ".scroll" elements stay visible, just without the entrance effect
                if (!entry.isIntersecting) {
                    element.classList.add("hidden");
                    return;
                }

                const animationClass = element.getAttribute("data-animation");
                element.classList.remove("hidden");
                if (animationClass) {
                    element.classList.add(animationClass);
                }
                this.observer.unobserve(entry.target);
            });
        }, { rootMargin: "0px 0px -200px 0px" });

        // The wrapper is "display: contents" and has no box to intersect, so its block's first drawn element is the one observed - never a <style> some kinds open with
        document.querySelectorAll(".scroll").forEach((element) => {
            const target = [...element.children].find((child) => !["STYLE", "SCRIPT", "LINK", "TEMPLATE"].includes(child.tagName));
            if (target) {
                this.observer.observe(target);
            }
        });
    }

    disconnect() {
        this.observer.disconnect();
    }
}
