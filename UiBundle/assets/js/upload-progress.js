/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// A form carrying files holds the screen for as long as the transfer lasts, plus whatever the server then does with what it received - a batch of fifty photos to resize, convert and watermark is minutes of a page showing nothing at all, which reads as a click that never registered and is clicked again. Sends the form over XMLHttpRequest instead, the only api a browser reports upload progress on (fetch reports the download, never the upload), and tells the two phases apart: the transfer, which has a percentage, and the processing that follows, which has none
export default class extends Controller {
    static targets = ["panel", "bar", "status", "submit"];

    static values = {
        uploadingMessage: String,
        processingMessage: String,
        failedMessage: String,
    };

    send(event) {
        this.form = this.formElement();

        // Nothing to take over: the controller is mounted somewhere holding no form at all, and the browser goes on submitting as it always did
        if (!this.form) {
            return;
        }

        // The form goes out from here on, a native submit alongside would send the whole batch twice
        event.preventDefault();

        const request = new XMLHttpRequest();
        request.open("POST", this.form.getAttribute("action") || window.location.href);
        // What lets the server answer with the url to land on rather than a redirect: XMLHttpRequest follows a 302 by itself, and the flash message the arrival page carries would be spent inside this request, on a page nobody ever sees (see UploadProgress::redirect)
        request.setRequestHeader("X-Requested-With", "XMLHttpRequest");
        request.upload.addEventListener("progress", (progress) => this.transferring(progress));
        // Fires once the last byte has left, everything after it being the server's own work
        request.upload.addEventListener("load", () => this.processing());
        request.addEventListener("load", () => this.done(request));
        request.addEventListener("error", () => this.failed());
        request.addEventListener("timeout", () => this.failed());

        // Read before the wait starts, begin() disabling the very button that was clicked and a disabled control being left out of what a form sends. The submitter goes in as an entry of its own, which is how EasyAdmin knows what screen to land on once saved (ea[newForm][btn])
        const data = new FormData(this.form, event.submitter);

        this.begin();
        request.send(data);
    }

    // The form itself where the controller sits on it, the one it wraps otherwise: EasyAdmin renders a form as a whole, leaving a screen adding fields to it nowhere to declare attributes but a div around it - and a submit event reaches that div all the same, events bubbling
    formElement() {
        return this.element.closest("form") ?? this.element.querySelector("form");
    }

    // The submit is taken away for the whole wait, a second click being what this screen invites and what would send the batch again
    begin() {
        // The panel already standing in the form is taken back rather than a second one built beside it: a failed transfer hands the submit back, and the next attempt would otherwise pile a fresh bar under the message of the one before. Looked up on the form itself, so it survives the form being swapped by restore()
        const panel = this.hasPanelTarget
            ? this.panelTarget
            : (this.form.querySelector(".upload-progress") ?? this.build());
        panel.hidden = false;
        this.bar = this.hasBarTarget ? this.barTarget : panel.querySelector("progress");
        // Shown again, failed() having hidden it on the previous attempt
        this.bar.hidden = false;
        this.line = this.hasStatusTarget ? this.statusTarget : panel.querySelector("p");
        this.toggleSubmit(true);
    }

    // Built here rather than asked of every template that wants the behavior: a form declares the controller and nothing else, and one rendering a panel of its own keeps it through the targets
    // Placed above the submit that started the wait rather than at the end of the form, which on an EasyAdmin screen is below the whole action row
    build() {
        const panel = document.createElement("div");
        panel.className = "upload-progress";

        const bar = document.createElement("progress");
        bar.className = "upload-progress-bar";
        bar.max = 100;

        const status = document.createElement("p");
        status.className = "upload-progress-status";
        // Announced, not merely displayed: from the submit on, the wait is what the screen is about
        status.setAttribute("role", "status");

        panel.append(bar, status);

        const [submit] = this.submitButtons();
        if (submit) {
            submit.before(panel);
        } else {
            this.form.append(panel);
        }

        return panel;
    }

    // Only what was actually measured: a transfer whose total the browser can't state leaves the bar where it stands rather than jumping to a made-up figure
    transferring(progress) {
        if (!progress.lengthComputable) {
            return;
        }

        const percent = Math.round((progress.loaded / progress.total) * 100);
        this.bar.value = percent;
        this.line.textContent = this.fill(this.uploadingMessageValue, {
            "%percent%": percent,
            "%sent%": this.megabytes(progress.loaded),
            "%total%": this.megabytes(progress.total),
        });
    }

    // A <progress> holding no value is the browser's own indeterminate bar, which is exactly what this phase is: nothing here can tell how far the server has got through the batch, and a bar frozen at 100% reads as a page that has stopped
    processing() {
        this.bar.removeAttribute("value");
        this.line.textContent = this.processingMessageValue;
    }

    done(request) {
        // Only a transfer that never landed: a request cut short (status 0) or a server that broke on it. A form the server rejects comes back as html holding its validation errors - Symfony renders an invalid one again with a 422 - and belongs to restore(), not to a message telling the user to check a connection that worked
        if (0 === request.status || request.status >= 500) {
            this.failed();

            return;
        }

        const redirect = this.redirect(request);
        if (redirect) {
            window.location.assign(redirect);

            return;
        }

        // Anything else is this very form rendered again with the errors the server found: swapping the two reconnects every controller the new one carries, Stimulus watching the document, where reloading would take the screen back to a blank form
        this.restore(request.responseText);
    }

    // The url a successful submission is answered with, json being the one shape that isn't the form coming back
    redirect(request) {
        if (!request.getResponseHeader("Content-Type")?.includes("application/json")) {
            return null;
        }

        try {
            return JSON.parse(request.responseText).redirect ?? null;
        } catch {
            return null;
        }
    }

    restore(html) {
        const parsed = new DOMParser().parseFromString(html, "text/html");
        const form = this.form.id ? parsed.getElementById(this.form.id) : parsed.querySelector("form");

        // A response holding no form of its own is one this controller can't read: the screen is loaded again rather than left on a bar that will never move
        if (!form) {
            window.location.reload();

            return;
        }

        this.form.replaceWith(form);
    }

    // The submit is handed back: a batch the network refused is one to send again, and the selection that was made is still on the screen
    failed() {
        this.bar.hidden = true;
        this.line.textContent = this.failedMessageValue;
        this.toggleSubmit(false);
    }

    toggleSubmit(disabled) {
        for (const button of this.submitButtons()) {
            button.disabled = disabled;
        }
    }

    // Falls back to whatever submit the form holds, EasyAdmin rendering the buttons of its own screens and leaving no place to declare a target on them
    submitButtons() {
        return this.hasSubmitTarget ? this.submitTargets : Array.from(this.form.querySelectorAll('[type="submit"]'));
    }

    fill(message, replacements) {
        return Object.entries(replacements).reduce(
            (text, [placeholder, value]) => text.replaceAll(placeholder, String(value)),
            message
        );
    }

    // One decimal: a batch of 112.4 MB has to read as a size, not as a rounded 112
    megabytes(bytes) {
        return (bytes / (1024 * 1024)).toFixed(1);
    }
}
