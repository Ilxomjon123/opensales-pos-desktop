<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, AtSign, Briefcase, KeyRound, Phone, ShieldCheck, User } from 'lucide-vue-next';
import { toRef } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import PhoneInput from '@/components/PhoneInput.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { Spinner } from '@/components/ui/spinner';
import UsernameStatusBadge from '@/components/UsernameStatusBadge.vue';
import { useUsernameAvailability } from '@/composables/useUsernameAvailability';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useI18n();

type RoleOption = { value: string; label: string };
type Employee = {
    id: number;
    name: string;
    username: string;
    phone: string | null;
    role: string;
    role_label: string;
};

const props = defineProps<{
    employee: { data: Employee };
    roles: RoleOption[];
}>();

const e = props.employee.data;

const form = useForm({
    name: e.name,
    username: e.username,
    phone: e.phone ?? '',
    role: e.role,
    password: '',
    password_confirmation: '',
});

const { status: usernameStatus } = useUsernameAvailability(toRef(form, 'username'), {
    ignoreId: e.id,
    initialValue: e.username,
});

function submit() {
    form.put(`/dealer/employees/${e.id}`);
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="`${e.name} — ${t('pageDealer.deliverymen.editTitleSuffix')}`" />

    <div class="mx-auto w-full max-w-4xl p-4 md:p-8">
        <div class="mb-6 flex items-center gap-3 sm:mb-8 sm:gap-4">
            <Button variant="ghost" size="icon" class="shrink-0" @click="router.get('/dealer/employees')">
                <ArrowLeft class="h-5 w-5" />
            </Button>
            <div class="min-w-0">
                <h1 class="truncate text-xl font-bold tracking-tight sm:text-2xl">{{ e.name }}</h1>
                <p class="truncate text-sm text-muted-foreground">{{ e.role_label }} · {{ e.username }}</p>
            </div>
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <!-- Rol -->
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-1">
                    <div class="flex items-start gap-3">
                        <div class="rounded-lg bg-primary/10 p-2 text-primary">
                            <ShieldCheck class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="font-semibold">{{ t('pageDealer.employees.role') }}</h3>
                            <p class="mt-1 text-sm text-muted-foreground">{{ t('pageDealer.employees.editRoleDesc') }}</p>
                        </div>
                    </div>
                </div>

                <Card class="lg:col-span-2">
                    <CardContent class="space-y-2 p-6">
                        <Label class="mb-1.5">{{ t('pageDealer.employees.role') }} <span class="text-destructive">*</span></Label>
                        <SearchableSelect
                            v-model="form.role"
                            :items="roles"
                            value-key="value"
                            label-key="label"
                            :placeholder="t('pageDealer.employees.rolePlaceholder')"
                        />
                        <InputError :message="form.errors.role" />
                    </CardContent>
                </Card>
            </div>

            <!-- Shaxsiy ma'lumotlar -->
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-1">
                    <div class="flex items-start gap-3">
                        <div class="rounded-lg bg-primary/10 p-2 text-primary">
                            <Briefcase class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="font-semibold">{{ t('pageDealer.employees.personalInfo') }}</h3>
                            <p class="mt-1 text-sm text-muted-foreground">{{ t('pageDealer.employees.editPersonalInfoDesc') }}</p>
                        </div>
                    </div>
                </div>

                <Card class="lg:col-span-2">
                    <CardContent class="space-y-5 p-6">
                        <div>
                            <Label for="name" class="mb-1.5 flex items-center gap-1.5">
                                <User class="h-3.5 w-3.5 text-muted-foreground" />
                                {{ t('pageDealer.employees.fullName') }} <span class="text-destructive">*</span>
                            </Label>
                            <Input id="name" v-model="form.name" required />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label for="username" class="mb-1.5 flex items-center gap-1.5">
                                    <AtSign class="h-3.5 w-3.5 text-muted-foreground" />
                                    {{ t('pageDealer.common.username') }} <span class="text-destructive">*</span>
                                </Label>
                                <Input id="username" v-model="form.username" autocomplete="username" required />
                                <UsernameStatusBadge :status="usernameStatus" />
                                <InputError :message="form.errors.username" />
                            </div>
                            <div>
                                <Label for="phone" class="mb-1.5 flex items-center gap-1.5">
                                    <Phone class="h-3.5 w-3.5 text-muted-foreground" />
                                    {{ t('pageDealer.common.phone') }}
                                </Label>
                                <PhoneInput v-model="form.phone" :error="!!form.errors.phone" />
                                <InputError :message="form.errors.phone" />
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Parolni o'zgartirish (ixtiyoriy) -->
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-1">
                    <div class="flex items-start gap-3">
                        <div class="rounded-lg bg-primary/10 p-2 text-primary">
                            <KeyRound class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="font-semibold">{{ t('pageDealer.deliverymen.changePassword') }}</h3>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ t('pageDealer.employees.changePasswordDesc') }}
                            </p>
                        </div>
                    </div>
                </div>

                <Card class="lg:col-span-2">
                    <CardContent class="grid gap-4 p-6 sm:grid-cols-2">
                        <div>
                            <Label for="password" class="mb-1.5">{{ t('pageDealer.common.newPassword') }}</Label>
                            <PasswordInput id="password" v-model="form.password" />
                            <InputError :message="form.errors.password" />
                        </div>
                        <div>
                            <Label for="password_confirmation" class="mb-1.5">{{ t('pageDealer.common.passwordRepeat') }}</Label>
                            <PasswordInput id="password_confirmation" v-model="form.password_confirmation" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div class="sticky bottom-0 z-10 -mx-4 flex items-center justify-end gap-3 border-t bg-background/80 px-4 py-4 backdrop-blur md:-mx-8 md:px-8">
                <Button variant="outline" type="button" @click="router.get('/dealer/employees')">
                    {{ t('pageDealer.employees.cancel') }}
                </Button>
                <Button type="submit" :disabled="form.processing" class="min-w-[140px]">
                    <Spinner v-if="form.processing" class="mr-2" />
                    {{ form.processing ? t('pageDealer.employees.saving') : t('pageDealer.employees.save') }}
                </Button>
            </div>
        </form>
    </div>
</template>
