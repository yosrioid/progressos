<script setup lang="ts">
import { BarElement, CategoryScale, Chart, LinearScale, Tooltip } from 'chart.js';
import { onMounted, onUnmounted, ref, watch } from 'vue';

Chart.register(CategoryScale, LinearScale, BarElement, Tooltip);

const props = defineProps<{
  labels: string[];
  values: number[];
  color?: string;
  formatValue?: (v: number) => string;
}>();

const canvas = ref<HTMLCanvasElement | null>(null);
let chart: Chart<'bar'> | null = null;
let observer: MutationObserver | null = null;

function buildChart() {
  if (!canvas.value) return;
  const isDark = document.documentElement.classList.contains('dark');
  const color = props.color ?? (isDark ? '#14b8a6' : '#0f766e');
  const gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';
  const labelColor = isDark ? '#a1a1aa' : '#64748b';

  chart?.destroy();
  chart = new Chart(canvas.value, {
    type: 'bar',
    data: {
      labels: props.labels,
      datasets: [{ data: props.values, backgroundColor: color, borderRadius: 6, borderSkipped: false }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: (ctx) => props.formatValue ? props.formatValue(ctx.parsed.y) : String(ctx.parsed.y),
          },
        },
      },
      scales: {
        x: { grid: { color: gridColor }, ticks: { color: labelColor, font: { size: 11, weight: 'bold' } } },
        y: { grid: { color: gridColor }, ticks: { color: labelColor, font: { size: 11 } }, beginAtZero: true },
      },
    },
  });
}

onMounted(() => {
  observer = new MutationObserver(buildChart);
  observer.observe(document.documentElement, { attributeFilter: ['class'] });
  buildChart();
});

watch(() => [props.labels, props.values], buildChart, { deep: true });

onUnmounted(() => {
  chart?.destroy();
  observer?.disconnect();
});
</script>

<template>
  <div class="relative h-48">
    <canvas ref="canvas" />
  </div>
</template>
