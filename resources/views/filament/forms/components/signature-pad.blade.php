@php
    $statePath = $getStatePath();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            state: $wire.$entangle('{{ $statePath }}'),
            init() {
                const canvas = this.$refs.canvas;
                const context = canvas.getContext('2d');
                let drawing = false;

                const resize = () => {
                    const ratio = window.devicePixelRatio || 1;
                    const rect = canvas.getBoundingClientRect();
                    canvas.width = rect.width * ratio;
                    canvas.height = 180 * ratio;
                    context.scale(ratio, ratio);
                    context.lineWidth = 2;
                    context.lineCap = 'round';
                    context.strokeStyle = '#1f2937';

                    if (this.state) {
                        const image = new Image();
                        image.onload = () => context.drawImage(image, 0, 0, rect.width, 180);
                        image.src = this.state;
                    }
                };

                const position = (event) => {
                    const rect = canvas.getBoundingClientRect();
                    const point = event.touches ? event.touches[0] : event;

                    return {
                        x: point.clientX - rect.left,
                        y: point.clientY - rect.top,
                    };
                };

                const start = (event) => {
                    drawing = true;
                    const point = position(event);
                    context.beginPath();
                    context.moveTo(point.x, point.y);
                    event.preventDefault();
                };

                const draw = (event) => {
                    if (!drawing) return;
                    const point = position(event);
                    context.lineTo(point.x, point.y);
                    context.stroke();
                    this.state = canvas.toDataURL('image/png');
                    event.preventDefault();
                };

                const stop = () => {
                    drawing = false;
                };

                resize();
                window.addEventListener('resize', resize);
                canvas.addEventListener('mousedown', start);
                canvas.addEventListener('mousemove', draw);
                canvas.addEventListener('mouseup', stop);
                canvas.addEventListener('mouseleave', stop);
                canvas.addEventListener('touchstart', start, { passive: false });
                canvas.addEventListener('touchmove', draw, { passive: false });
                canvas.addEventListener('touchend', stop);
            },
            clear() {
                const canvas = this.$refs.canvas;
                const context = canvas.getContext('2d');
                context.clearRect(0, 0, canvas.width, canvas.height);
                this.state = '';
            },
        }"
        class="space-y-3 {{ $isDisabled() ? 'pointer-events-none opacity-90' : '' }}"
    >
        <canvas
            x-ref="canvas"
            style="width: 100%; height: 180px; border: 1px dashed #cbd5e1; border-radius: 0.75rem; background: #fff;"
        ></canvas>

        @if (! $isDisabled())
        <div class="flex justify-end">
            <button
                type="button"
                x-on:click="clear()"
                class="fi-btn fi-color-gray fi-size-sm fi-btn-color-gray rounded-lg px-3 py-2 text-sm font-medium"
            >
                Limpar assinatura
            </button>
        </div>
        @endif
    </div>
</x-dynamic-component>
