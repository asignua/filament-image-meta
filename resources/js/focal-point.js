/*
 * Alpine component of the focal-point picker. Fetched on demand by Alpine's x-load, only on a page
 * that opens the "Edit details" modal.
 *
 * The state is `{x, y}` in percent (0..100, two decimals) or null ("not set", i.e. the centre).
 * Pointer events cover mouse, touch and pen; the marker is a keyboard slider as well.
 */
export default function imageMetaFocalPoint({ state, labels = {}, step = 1, bigStep = 10 }) {
    const clamp = (value) => Math.min(100, Math.max(0, Math.round(value * 100) / 100))

    return {
        state,

        dragging: false,

        get isSet() {
            return this.state !== null && this.state !== undefined && this.state.x !== undefined && this.state.y !== undefined
        },

        get x() {
            return this.isSet ? this.state.x : 50
        },

        get y() {
            return this.isSet ? this.state.y : 50
        },

        get text() {
            return this.isSet
                ? (labels.set ?? ':x / :y').replace(':x', Math.round(this.x)).replace(':y', Math.round(this.y))
                : (labels.unset ?? '')
        },

        // An OBJECT, not a string: Alpine writes a string into the whole `style` attribute and would wipe
        // the marker's static `position:absolute` / size / border (the marker disappeared).
        markerStyle() {
            return { left: `${this.x}%`, top: `${this.y}%`, opacity: this.isSet ? 1 : 0.55 }
        },

        // `max-height` on the image shrinks it AFTER the shrink-to-fit wrapper took the wider width, so the
        // wrapper (the coordinate space) became wider than the picture and the point landed off the image.
        // Pin the wrapper to the width the image really renders at.
        fit() {
            const surface = this.$refs.surface
            const image = surface?.querySelector('img')

            if (!image) {
                return
            }

            surface.style.width = ''

            // offsetWidth, not getBoundingClientRect(): the modal is still scaling in (a transform) when this first runs.
            const width = image.offsetWidth

            if (width > 0) {
                surface.style.width = `${width}px`
            }
        },

        init() {
            this.$nextTick(() => {
                this.fit()

                if (typeof ResizeObserver !== 'undefined') {
                    this.observer = new ResizeObserver(() => this.fit())
                    this.observer.observe(this.$el)
                }
            })
        },

        destroy() {
            this.observer?.disconnect()
        },

        place(event) {
            const rect = this.$refs.surface.getBoundingClientRect()

            if (rect.width === 0 || rect.height === 0) {
                return
            }

            this.state = {
                x: clamp(((event.clientX - rect.left) / rect.width) * 100),
                y: clamp(((event.clientY - rect.top) / rect.height) * 100),
            }
        },

        start(event) {
            if (event.button !== undefined && event.button !== 0) {
                return
            }

            this.dragging = true
            this.$refs.surface.setPointerCapture?.(event.pointerId)
            this.place(event)
        },

        move(event) {
            if (this.dragging) {
                this.place(event)
            }
        },

        stop(event) {
            this.dragging = false
            this.$refs.surface.releasePointerCapture?.(event.pointerId)
        },

        key(event) {
            const amount = event.shiftKey ? bigStep : step

            const moves = {
                ArrowLeft: [-amount, 0],
                ArrowRight: [amount, 0],
                ArrowUp: [0, -amount],
                ArrowDown: [0, amount],
            }

            // Not Escape: inside a modal it closes the dialog (and means "cancel" there).
            if (event.key === 'Home') {
                event.preventDefault()
                this.reset()

                return
            }

            if (!(event.key in moves)) {
                return
            }

            event.preventDefault()

            const [dx, dy] = moves[event.key]

            this.state = { x: clamp(this.x + dx), y: clamp(this.y + dy) }
        },

        reset() {
            this.state = null
        },
    }
}
