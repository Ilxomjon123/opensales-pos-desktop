<script setup lang="ts">
import { Form, Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';

defineProps<{
    status?: string;
}>();

const { t } = useI18n();

watchEffect(() => {
    setLayoutProps({
        title: t('pageAuth.login.title'),
        description: t('pageAuth.login.description'),
    });
});
</script>

<template>
    <Head :title="t('pageAuth.login.headTitle')" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="username">{{ t('pageAuth.login.username') }}</Label>
                <Input
                    id="username"
                    type="text"
                    name="username"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="username"
                    :placeholder="t('pageAuth.login.usernamePlaceholder')"
                />
                <InputError :message="errors.username" />
            </div>

            <div class="grid gap-2">
                <Label for="password">{{ t('pageAuth.login.password') }}</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    :placeholder="t('pageAuth.login.passwordPlaceholder')"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>{{ t('pageAuth.login.remember') }}</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                {{ t('pageAuth.login.submit') }}
            </Button>
        </div>
    </Form>

    <div class="mt-6 text-center text-sm text-muted-foreground">
        {{ t('pageAuth.login.noAccount') }}
        <Link href="/register" class="font-medium text-foreground underline" :tabindex="5">
            {{ t('pageAuth.login.registerLink') }}
        </Link>
    </div>
</template>
