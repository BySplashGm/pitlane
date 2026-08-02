import { Controller } from '@hotwired/stimulus';

/*
 * Polls the plain-text server-log endpoint every few seconds and mirrors the newest lines into the
 * <pre> element. Living in a Stimulus controller means disconnect() runs on Turbo navigation, so the
 * timer and any in-flight request are torn down instead of leaking across pages.
 */
export default class extends Controller {
    static values = {
        url: String,
        interval: { type: Number, default: 5000 },
    };

    connect() {
        this.refresh();
        this.timer = window.setInterval(() => this.refresh(), this.intervalValue);
    }

    disconnect() {
        window.clearInterval(this.timer);
        this.controller?.abort();
    }

    async refresh() {
        // Abort a still-pending request before starting the next tick so slow responses cannot stack.
        this.controller?.abort();
        this.controller = new AbortController();

        try {
            const response = await fetch(this.urlValue, {
                headers: { Accept: 'text/plain' },
                signal: this.controller.signal,
            });
            if (!response.ok) {
                return;
            }

            const atBottom = this.element.scrollHeight - this.element.scrollTop - this.element.clientHeight < 20;
            this.element.textContent = (await response.text()) || 'No logs yet.';
            if (atBottom) {
                this.element.scrollTop = this.element.scrollHeight;
            }
        } catch (error) {
            // A transient fetch failure or an abort is ignored: the next tick retries.
        }
    }
}
