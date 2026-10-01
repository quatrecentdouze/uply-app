import { Link } from '@tanstack/react-router';
import { useTranslation } from 'react-i18next';
import { useCurrentOrganization } from '@/features/organizations/hooks/currentOrganization';
import { roleTextKeys } from '@/features/organizations/model/organizations';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/shared/ui/dropdown-menu';
import { Building2, ChevronsUpDown, Plus } from '@/shared/ui/icons';

export function OrganizationSwitcher({ compact = false }: { compact?: boolean }) {
  const { t } = useTranslation(['organizations']);
  const { organization, organizations, select } = useCurrentOrganization();
  const role = t(roleTextKeys[organization.role]);

  return (
    <DropdownMenu>
      <DropdownMenuTrigger
        aria-label={t('organizations:switcher.open', { organization: organization.name })}
        className={
          compact
            ? 'mx-auto grid size-[50px] place-items-center rounded-[15px] bg-linear-135 from-glow-start/25 via-surface-raised to-surface-raised text-foreground'
            : 'flex w-full items-center gap-3 rounded-[15px] bg-linear-135 from-glow-start/20 via-surface-raised to-surface-raised px-4 py-4 text-left outline-none focus-visible:ring-2 focus-visible:ring-ring'
        }
      >
        <span className="grid size-10 shrink-0 place-items-center rounded-[12px] bg-primary/15 text-primary">
          <Building2 className="size-5" aria-hidden="true" />
        </span>
        {compact ? null : (
          <>
            <span className="grid min-w-0 flex-1 gap-1">
              <span className="truncate text-lg leading-6 font-normal">{organization.name}</span>
              <span className="text-sm text-muted-foreground">{role}</span>
            </span>
            <ChevronsUpDown className="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
          </>
        )}
      </DropdownMenuTrigger>
      <DropdownMenuContent side="top" align="start" className="w-[var(--radix-dropdown-menu-trigger-width)] min-w-64">
        <DropdownMenuLabel className="text-faint">{t('organizations:switcher.label')}</DropdownMenuLabel>
        <DropdownMenuRadioGroup value={organization.id} onValueChange={select}>
          {organizations.map((candidate) => (
            <DropdownMenuRadioItem key={candidate.id} value={candidate.id}>
              <span className="truncate">{candidate.name}</span>
            </DropdownMenuRadioItem>
          ))}
        </DropdownMenuRadioGroup>
        <DropdownMenuSeparator />
        <DropdownMenuItem asChild>
          <Link to="/organizations/new">
            <Plus aria-hidden="true" />
            {t('organizations:switcher.create')}
          </Link>
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
