import { useId, useState } from 'react';
import { Area, AreaChart, Bar, BarChart, type BarShapeProps, CartesianGrid, Rectangle, Tooltip, XAxis, YAxis } from 'recharts';
import { ChartContainer } from '@/shared/ui/chart';

export interface TrendPoint {
  key: string;
  label: string;
  tooltip: string;
  value: number | null;
  display: string;
}

interface TrendProps {
  points: TrendPoint[];
  ariaLabel: string;
}

interface TooltipEntry {
  payload?: unknown;
}

function TrendTooltip({ points, active, payload }: { points: readonly TrendPoint[]; active?: boolean; payload?: readonly TooltipEntry[] }) {
  const entry = payload?.[0];
  const point = points.find((candidate) => candidate === entry?.payload);
  if (active !== true || point === undefined || point.value === null) {
    return null;
  }
  return (
    <div className="flex items-center gap-2 rounded-[13px] border-2 border-line-strong bg-secondary px-3.5 py-2 whitespace-nowrap">
      <span className="text-base text-muted-foreground">{point.tooltip}</span>
      <strong className="text-lg font-normal tracking-[-0.4px] text-foreground">{point.display}</strong>
    </div>
  );
}

const axisTick = { fill: 'var(--color-muted-foreground)', fontSize: 14 };

export function AreaTrend({ points, ariaLabel, floor }: TrendProps & { floor: number }) {
  const id = useId().replace(/:/g, '');
  return (
    <ChartContainer config={{ value: { label: ariaLabel, color: 'var(--color-primary)' } }} role="img" aria-label={ariaLabel} className="aspect-auto h-full w-full">
      <AreaChart data={points} margin={{ top: 10, right: 4, bottom: 0, left: 4 }}>
        <defs>
          <linearGradient id={`${id}-fill`} x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stopColor="var(--color-primary)" stopOpacity={0.22} />
            <stop offset="1" stopColor="var(--color-primary)" stopOpacity={0.015} />
          </linearGradient>
          <linearGradient id={`${id}-stroke`} x1="0" y1="0" x2="1" y2="0">
            <stop offset="0" stopColor="var(--color-glow-gold)" />
            <stop offset="0.45" stopColor="var(--color-primary)" />
            <stop offset="0.8" stopColor="var(--color-glow-blue)" />
            <stop offset="1" stopColor="var(--color-glow-orange)" />
          </linearGradient>
        </defs>
        <CartesianGrid vertical horizontal={false} stroke="var(--color-line-strong)" strokeDasharray="4 4" />
        <XAxis dataKey="label" tickLine={false} axisLine={false} minTickGap={28} tick={axisTick} tickMargin={6} />
        <YAxis hide domain={[floor, 100]} />
        <Tooltip cursor={{ stroke: 'var(--color-primary)', strokeWidth: 2 }} content={<TrendTooltip points={points} />} />
        <Area
          type="monotone"
          dataKey="value"
          stroke={`url(#${id}-stroke)`}
          strokeWidth={2}
          fill={`url(#${id}-fill)`}
          activeDot={{ r: 7, fill: 'var(--color-glow-gold)', stroke: 'var(--color-bright)', strokeWidth: 2 }}
          isAnimationActive={false}
        />
      </AreaChart>
    </ChartContainer>
  );
}

function lastValueIndex(points: readonly TrendPoint[]): number {
  return points.findLastIndex((point) => point.value !== null);
}

export function BarTrend({ points, ariaLabel, formatTick }: TrendProps & { formatTick: (value: number) => string }) {
  const id = useId().replace(/:/g, '');
  const [hovered, setHovered] = useState<number | null>(null);
  const selected = hovered ?? lastValueIndex(points);
  return (
    <ChartContainer config={{ value: { label: ariaLabel, color: 'var(--color-primary)' } }} role="img" aria-label={ariaLabel} className="aspect-auto h-full w-full">
      <BarChart
        data={points}
        margin={{ top: 10, right: 0, bottom: 0, left: 0 }}
        barCategoryGap={6}
        onMouseMove={(state) => {
          setHovered(typeof state.activeTooltipIndex === 'number' ? state.activeTooltipIndex : null);
        }}
        onMouseLeave={() => {
          setHovered(null);
        }}
      >
        <defs>
          <linearGradient id={`${id}-rest`} x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stopColor="var(--color-bar-top)" />
            <stop offset="1" stopColor="var(--color-bar-bottom)" />
          </linearGradient>
          <linearGradient id={`${id}-active`} x1="0" y1="1" x2="1" y2="0">
            <stop offset="0.06" stopColor="var(--color-glow-start)" />
            <stop offset="0.35" stopColor="var(--color-glow-orange)" />
            <stop offset="0.57" stopColor="var(--color-glow-gold)" />
            <stop offset="0.93" stopColor="var(--color-glow-blue)" />
          </linearGradient>
        </defs>
        <CartesianGrid vertical={false} stroke="var(--color-line-strong)" strokeDasharray="4 4" />
        <XAxis dataKey="label" tickLine={false} axisLine={false} minTickGap={16} tick={axisTick} tickMargin={6} />
        <YAxis width={56} tickLine={false} axisLine={false} tick={axisTick} tickCount={4} tickFormatter={formatTick} />
        <Tooltip cursor={false} content={<TrendTooltip points={points} />} />
        <Bar
          dataKey="value"
          radius={[12, 12, 9, 9]}
          maxBarSize={52}
          isAnimationActive={false}
          shape={(bar: BarShapeProps) => <Rectangle {...bar} fill={bar.originalDataIndex === selected ? `url(#${id}-active)` : `url(#${id}-rest)`} />}
        />
      </BarChart>
    </ChartContainer>
  );
}
