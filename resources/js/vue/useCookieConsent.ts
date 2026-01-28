/**
 * Cookie Consent Composable for Vue
 *
 * @package wireboard/laravel-cmp
 *
 * Usage:
 *   <script setup>
 *   import { useCookieConsent } from '@/composables/useCookieConsent';
 *
 *   const { showPreferences, hasValidConsent } = useCookieConsent();
 *   </script>
 */

export function useCookieConsent() {
  const showPreferences = () => {
    if (typeof window !== 'undefined' && (window as any).CookieConsent) {
      (window as any).CookieConsent.showPreferences();
    }
  };

  const resetConsent = (reload = true) => {
    if (typeof window !== 'undefined' && (window as any).CookieConsent) {
      (window as any).CookieConsent.reset(reload);
    }
  };

  const hasValidConsent = () => {
    if (typeof window !== 'undefined' && (window as any).CookieConsent) {
      return (window as any).CookieConsent.validConsent();
    }
    return false;
  };

  const acceptCategory = (category: string | string[]) => {
    if (typeof window !== 'undefined' && (window as any).CookieConsent) {
      (window as any).CookieConsent.acceptCategory(category);
    }
  };

  const hasAcceptedCategory = (category: string) => {
    if (typeof window !== 'undefined' && (window as any).CookieConsent) {
      return (window as any).CookieConsent.acceptedCategory(category);
    }
    return false;
  };

  const getCmpType = (): 'google' | 'custom' | undefined => {
    if (typeof window !== 'undefined') {
      return (window as any).__cmpType;
    }
    return undefined;
  };

  return {
    showPreferences,
    resetConsent,
    hasValidConsent,
    acceptCategory,
    hasAcceptedCategory,
    getCmpType,
  };
}

export default useCookieConsent;
