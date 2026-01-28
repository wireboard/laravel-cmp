/**
 * Cookie Preferences Link Component for React/Inertia.js
 *
 * @package wireboard/laravel-cmp
 *
 * Usage:
 *   import { CookiePreferencesLink } from '@/components/CookiePreferencesLink';
 *
 *   <CookiePreferencesLink />
 *   <CookiePreferencesLink text="Manage Cookies" className="text-sm text-gray-500" />
 */

import { ButtonHTMLAttributes } from 'react';

// Extend Window interface for CookieConsent
declare global {
    interface Window {
        CookieConsent?: {
            showPreferences: () => void;
            show: () => void;
            hide: () => void;
            reset: (reload?: boolean) => void;
            validConsent: () => boolean;
            acceptCategory: (category: string | string[]) => void;
            acceptedCategory: (category: string) => boolean;
        };
        __cmpType?: 'google' | 'custom';
    }
}

interface CookiePreferencesLinkProps extends Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'onClick'> {
    /**
     * The text to display. Defaults to "Cookie Settings"
     */
    text?: string;

    /**
     * Render as a different element (for styling as a link)
     */
    as?: 'button' | 'span';
}

export function CookiePreferencesLink({
    text = 'Cookie Settings',
    as = 'button',
    className = '',
    ...props
}: CookiePreferencesLinkProps) {
    const handleClick = () => {
        // Only works with Custom CMP (vanilla-cookieconsent)
        if (typeof window !== 'undefined' && window.CookieConsent) {
            window.CookieConsent.showPreferences();
        }
    };

    const Component = as;

    return (
        <Component
            type={as === 'button' ? 'button' : undefined}
            onClick={handleClick}
            className={className}
            style={{ cursor: 'pointer' }}
            {...props}
        >
            {text}
        </Component>
    );
}

/**
 * Hook to access CookieConsent API
 */
export function useCookieConsent() {
    const showPreferences = () => {
        if (typeof window !== 'undefined' && window.CookieConsent) {
            window.CookieConsent.showPreferences();
        }
    };

    const resetConsent = (reload = true) => {
        if (typeof window !== 'undefined' && window.CookieConsent) {
            window.CookieConsent.reset(reload);
        }
    };

    const hasValidConsent = () => {
        if (typeof window !== 'undefined' && window.CookieConsent) {
            return window.CookieConsent.validConsent();
        }
        return false;
    };

    const acceptCategory = (category: string | string[]) => {
        if (typeof window !== 'undefined' && window.CookieConsent) {
            window.CookieConsent.acceptCategory(category);
        }
    };

    const hasAcceptedCategory = (category: string) => {
        if (typeof window !== 'undefined' && window.CookieConsent) {
            return window.CookieConsent.acceptedCategory(category);
        }
        return false;
    };

    const getCmpType = () => {
        if (typeof window !== 'undefined') {
            return window.__cmpType;
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

export default CookiePreferencesLink;
