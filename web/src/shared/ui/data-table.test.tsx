import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { cellClass, DataTable, secondaryCell } from '@/shared/ui/data-table';

describe('DataTable', () => {
  it('hides the secondary columns under the md breakpoint', () => {
    render(
      <DataTable label="Things" head={[{ label: 'Name' }, { label: 'Created', secondary: true }]}>
        <tr>
          <td className={cellClass}>One</td>
          <td className={`${cellClass} ${secondaryCell}`}>Yesterday</td>
        </tr>
      </DataTable>,
    );

    expect(screen.getByRole('columnheader', { name: 'Created' })).toHaveClass('max-md:hidden');
    expect(screen.getByRole('columnheader', { name: 'Name' })).not.toHaveClass('max-md:hidden');
    expect(screen.getByRole('cell', { name: 'Yesterday' })).toHaveClass('max-md:hidden');
  });
});
