import { computed, onMounted, onUnmounted, ref } from 'vue';

/**
 * Estado y acciones para instalar la app como PWA.
 *
 * El evento `beforeinstallprompt` se captura en un script inline del `<head>`
 * (ver resources/views/app.blade.php) y se deja en `window.__pwaInstall`, porque
 * Chrome lo dispara antes de que monte Vue.
 */

type BeforeInstallPromptEvent = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

declare global {
    interface Window {
        __pwaInstall?: {
            prompt: BeforeInstallPromptEvent | null;
            installed: boolean;
        };
    }
}

const STANDALONE = '(display-mode: standalone)';

// iOS/iPadOS Safari nunca dispara beforeinstallprompt: la instalación es manual
// (Compartir → Agregar a inicio).
function detectarIosSafari(): boolean {
    const ua = navigator.userAgent;
    const esIos =
        /iPad|iPhone|iPod/.test(ua) ||
        // iPadOS se reporta como Mac; se distingue por el touch.
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

    // Chrome/Firefox/Edge en iOS son WebKit por dentro pero no instalan PWAs.
    return esIos && !/CriOS|FxiOS|EdgiOS|OPiOS/.test(ua);
}

function detectarInstalada(): boolean {
    return (
        window.matchMedia?.(STANDALONE).matches === true ||
        // iOS Safari expone esto cuando corre como app instalada.
        (window.navigator as Navigator & { standalone?: boolean })
            .standalone === true ||
        window.__pwaInstall?.installed === true
    );
}

export function usePwaInstall() {
    const instalada = ref(detectarInstalada());
    const esIos = detectarIosSafari();

    // Queda en true aunque el prompt ya se haya consumido, para caer en las
    // instrucciones manuales en vez de dejar el botón muerto.
    const ofrecido = ref(!!window.__pwaInstall?.prompt);

    const sePuedeInstalar = computed(
        () => !instalada.value && (ofrecido.value || esIos),
    );

    const alInstalable = () => (ofrecido.value = true);
    const alInstalada = () => (instalada.value = true);
    const alCambiarDisplayMode = (e: MediaQueryListEvent) =>
        (instalada.value = e.matches);
    const mq = window.matchMedia?.(STANDALONE);

    onMounted(() => {
        window.addEventListener('pwa:installable', alInstalable);
        window.addEventListener('pwa:installed', alInstalada);
        mq?.addEventListener?.('change', alCambiarDisplayMode);
    });
    onUnmounted(() => {
        window.removeEventListener('pwa:installable', alInstalable);
        window.removeEventListener('pwa:installed', alInstalada);
        mq?.removeEventListener?.('change', alCambiarDisplayMode);
    });

    /** 'accepted' | 'dismissed' | 'manual' (iOS o el prompt ya se usó). */
    async function instalar(): Promise<'accepted' | 'dismissed' | 'manual'> {
        const diferido = window.__pwaInstall?.prompt;
        if (!diferido) return 'manual';

        window.__pwaInstall!.prompt = null; // el evento sirve una sola vez
        await diferido.prompt();
        const { outcome } = await diferido.userChoice;
        return outcome;
    }

    return { sePuedeInstalar, instalada, esIos, instalar };
}
