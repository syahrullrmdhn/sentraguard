// SentraGuard dashboard scripts

/**
 * Lightweight metrics line chart rendered on a <canvas data-history='[...]'>.
 * Avoids external chart libraries to keep the bundle small. Draws CPU + RAM%
 * lines over the last hour of samples. Re-renders on Livewire DOM updates.
 */
function renderMetricsChart() {
    const canvas = document.getElementById('metrics-chart');
    if (!canvas) return;

    let history;
    try {
        history = JSON.parse(canvas.dataset.history || '[]');
    } catch (e) {
        history = [];
    }

    const ctx = canvas.getContext('2d');
    const dpr = window.devicePixelRatio || 1;
    const cssWidth = canvas.clientWidth || canvas.parentElement.clientWidth || 600;
    const cssHeight = 100;
    canvas.width = cssWidth * dpr;
    canvas.height = cssHeight * dpr;
    ctx.scale(dpr, dpr);
    ctx.clearRect(0, 0, cssWidth, cssHeight);

    if (!history.length) {
        ctx.fillStyle = '#3a3a38';
        ctx.font = '12px "Space Mono", monospace';
        ctx.fillText('Belum ada data metrik.', 8, 20);
        return;
    }

    const pad = 4;
    const w = cssWidth - pad * 2;
    const h = cssHeight - pad * 2;

    const cpu = history.map((p) => Number(p.cpu_percent) || 0);
    const ram = history.map((p) =>
        p.ram_total_mb ? (Number(p.ram_used_mb) / Number(p.ram_total_mb)) * 100 : 0
    );

    const drawLine = (series, color) => {
        ctx.beginPath();
        series.forEach((v, i) => {
            const x = pad + (series.length === 1 ? 0 : (i / (series.length - 1)) * w);
            const y = pad + h - (Math.min(v, 100) / 100) * h;
            i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
        });
        ctx.strokeStyle = color;
        ctx.lineWidth = 2;
        ctx.stroke();
    };

    // Swiss grid baseline
    ctx.strokeStyle = 'rgba(17,17,17,0.1)';
    ctx.lineWidth = 1;
    [0, 0.5, 1].forEach((f) => {
        const y = pad + h * f;
        ctx.beginPath();
        ctx.moveTo(pad, y);
        ctx.lineTo(pad + w, y);
        ctx.stroke();
    });

    drawLine(cpu, '#2d3dff'); // accent blue = CPU
    drawLine(ram, '#00c281'); // ok green = RAM
}

document.addEventListener('DOMContentLoaded', renderMetricsChart);
document.addEventListener('livewire:navigated', renderMetricsChart);
if (window.Livewire) {
    window.Livewire.hook('morph.updated', renderMetricsChart);
}
