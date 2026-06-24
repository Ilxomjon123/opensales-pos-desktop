import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { UserRole } from '@/types/auth';

const ROLE_LABELS: Record<UserRole, string> = {
    super_admin: 'Admin',
    dealer: 'Diller',
    warehouse: 'Warehouse',
    deliveryman: 'Delivery',
};

export function useRoleLabel() {
    const page = usePage();

    const role = computed<UserRole | undefined>(() => page.props.auth?.role);

    const roleLabel = computed<string | null>(() => {
        const value = role.value;
        return value ? (ROLE_LABELS[value] ?? null) : null;
    });

    const brandName = computed<string>(() => page.props.project?.name ?? '');

    const brandTitle = computed<string>(() =>
        roleLabel.value ? `${brandName.value} - ${roleLabel.value}` : brandName.value,
    );

    return { role, roleLabel, brandName, brandTitle };
}
