<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import UsernameStatusBadge from '@/components/UsernameStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useUsernameAvailability } from '@/composables/useUsernameAvailability';
import { edit } from '@/routes/profile';

const { t } = useI18n();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Profile settings',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

const usernameInput = ref<string>(String(user.value.username ?? ''));
const { status: usernameStatus } = useUsernameAvailability(usernameInput, {
    ignoreId: Number(user.value.id),
    initialValue: String(user.value.username ?? ''),
});
</script>

<template>
    <Head :title="t('pageSettings.profile.breadcrumb')" />

    <h1 class="sr-only">{{ t('pageSettings.profile.breadcrumb') }}</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            :title="t('pageSettings.profile.heading')"
            :description="t('pageSettings.profile.description')"
        />

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">{{ t('pageSettings.profile.name') }}</Label>
                <Input
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    :placeholder="t('pageSettings.profile.namePlaceholder')"
                />
                <InputError class="mt-2" :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="username">{{ t('pageSettings.profile.username') }}</Label>
                <Input
                    id="username"
                    type="text"
                    class="mt-1 block w-full"
                    name="username"
                    v-model="usernameInput"
                    required
                    autocomplete="username"
                    :placeholder="t('pageSettings.profile.usernamePlaceholder')"
                />
                <UsernameStatusBadge :status="usernameStatus" />
                <InputError class="mt-2" :message="errors.username" />
            </div>

            <div class="flex items-center gap-4">
                <Button :disabled="processing" data-test="update-profile-button">
                    {{ t('pageSettings.profile.save') }}
                </Button>
            </div>
        </Form>
    </div>

    <DeleteUser />
</template>
