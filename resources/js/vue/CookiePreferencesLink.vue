<!--
  Cookie Preferences Link Component for Vue/Inertia.js

  @package wireboard/laravel-cmp

  Usage:
    <script setup>
    import CookiePreferencesLink from '@/components/CookiePreferencesLink.vue';
    </script>

    <CookiePreferencesLink />
    <CookiePreferencesLink text="Manage Cookies" class="text-sm text-gray-500" />
-->

<script setup lang="ts">
interface Props {
  /**
   * The text to display. Defaults to "Cookie Settings"
   */
  text?: string;

  /**
   * Render as a different element
   */
  as?: 'button' | 'span';
}

const props = withDefaults(defineProps<Props>(), {
  text: 'Cookie Settings',
  as: 'button',
});

const handleClick = () => {
  if (typeof window !== 'undefined' && (window as any).CookieConsent) {
    (window as any).CookieConsent.showPreferences();
  }
};
</script>

<template>
  <component
    :is="props.as"
    :type="props.as === 'button' ? 'button' : undefined"
    @click="handleClick"
    style="cursor: pointer"
  >
    {{ props.text }}
  </component>
</template>
