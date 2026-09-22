/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Sends the visitor's question to AiSearchController and writes what comes back. The answer is written with textContent only: it is a model's text, and nothing it says is ever read as markup. The cards are the one html taken as such, rendered by the site's own templates from its own rows (see AiSearchCardProviderInterface)
export default class extends Controller {
    static targets = ["question", "submit", "busy", "result", "answer", "cards", "sourcesBlock", "sources"];

    static values = {
        url: String,
        locale: String,
        notFoundLabel: String,
        errorLabel: String,
        throttledLabel: String,
    };

    // A suggested question is asked as if typed, and left in the field to be reworded
    suggest(event) {
        this.questionTarget.value = event.params.question;
        this.ask(event);
    }

    async ask(event) {
        event.preventDefault();
        // The same bounds as the route's (AiSiteSearch::MIN_LENGTH, MAX_LENGTH), the field carrying no constraint of its own
        const question = this.questionTarget.value.trim().slice(0, 300);
        if (question.length < 3 || this.submitTarget.disabled) {
            return;
        }

        this.busy(true);
        try {
            const response = await fetch(this.urlValue, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                // Same-origin only, which is also what the route checks: it takes no csrf token
                credentials: "same-origin",
                // The page's own locale: the route has none, and searches the passages written in it
                body: JSON.stringify({ question: question, locale: this.localeValue }),
            });
            if (!response.ok) {
                throw new Error(String(response.status));
            }
            this.show(await response.json());
        } catch (error) {
            this.show({ answer: "429" === error.message ? this.throttledLabelValue : this.errorLabelValue, sources: [] });
        } finally {
            this.busy(false);
        }
    }

    busy(on) {
        this.submitTarget.disabled = on;
        this.busyTarget.classList.toggle("search-busy--on", on);
    }

    show(data) {
        this.answerTarget.textContent = data.answer || this.notFoundLabelValue;
        this.cardsTarget.innerHTML = data.cards || "";
        this.cardsTarget.hidden = "" === this.cardsTarget.innerHTML;
        // The lazy controllers a card carries (a heart, a basket button) are registered once they are in the page
        document.dispatchEvent(new CustomEvent("c975l:content-loaded"));
        this.sourcesTarget.replaceChildren(...(data.sources || []).map((source) => this.link(source)));
        this.sourcesBlockTarget.hidden = 0 === this.sourcesTarget.children.length;
        // Muted when nothing was found, so an answer and a dead end don't read alike
        this.resultTarget.classList.toggle("ai-search__result--empty", !data.found);
        // Shown, then faded in on the next frame: a transition never runs on an element that was not drawn yet
        this.resultTarget.classList.remove("ai-search__result--shown");
        this.resultTarget.hidden = false;
        requestAnimationFrame(() => this.resultTarget.classList.add("ai-search__result--shown"));
    }

    // The page's title, and under it its path, so the visitor sees where the link leads before following it
    link(source) {
        const item = document.createElement("li");
        const anchor = document.createElement("a");
        anchor.className = "ai-search__source";
        anchor.href = source.url;
        const title = document.createElement("span");
        title.className = "ai-search__source-title";
        title.textContent = source.title;
        const path = document.createElement("span");
        path.className = "ai-search__source-path";
        path.textContent = new URL(source.url, document.baseURI).pathname;
        anchor.append(title, path);
        item.append(anchor);

        return item;
    }
}
