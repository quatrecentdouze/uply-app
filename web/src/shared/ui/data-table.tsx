import { cn } from 'cn';
import type { ReactNode } from 'react';

export interface HeadCell {
  label: string;
  className?: string;
  hidden?: boolean;
}

interface DataTableProps {
  label: string;
  head: readonly HeadCell[];
  children: ReactNode;
  className?: string;
}

const headCell = 'h-[35px] border-y-2 border-line-strong bg-chip px-2.5 text-sm font-light whitespace-nowrap text-quiet 2xl:px-4';

export const rowClass = 'h-[58px] text-base';

export const cellClass = 'border-b-2 border-divider px-2.5 2xl:px-4';

export function DataTable({ label, head, children, className }: DataTableProps) {
  return (
    <div className="overflow-x-auto">
      <table aria-label={label} className={cn('w-full border-separate border-spacing-0 text-left', className)}>
        <thead>
          <tr>
            {head.map((cell, index) => (
              <th
                key={`${cell.label}-${String(index)}`}
                scope="col"
                className={cn(
                  headCell,
                  index === 0 && 'rounded-l-[10px] border-l-2 pl-[18px]',
                  index === head.length - 1 && 'rounded-r-[10px] border-r-2 pr-4',
                  cell.className,
                )}
              >
                {cell.hidden === true ? <span className="sr-only">{cell.label}</span> : cell.label}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>{children}</tbody>
      </table>
    </div>
  );
}
