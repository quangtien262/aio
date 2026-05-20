import { useState } from 'react';

const DEFAULT_OVERLAY_STATE = {
    type: null,
    themeKey: null,
};

export default function useThemeActionOverlayController() {
    const [overlayState, setOverlayState] = useState(DEFAULT_OVERLAY_STATE);

    const openAction = (type, theme) => {
        if (!theme?.key) {
            return;
        }

        setOverlayState({ type, themeKey: theme.key });
    };

    return {
        overlayState,
        closeOverlay: () => setOverlayState(DEFAULT_OVERLAY_STATE),
        openLocale: (theme) => openAction('locale', theme),
        openPalette: (theme) => openAction('palette', theme),
        openThemeTranslations: (theme) => openAction('theme-translate', theme),
        openFrontendTranslations: (theme) => openAction('frontend-translate', theme),
        openDemoCreate: (theme) => openAction('demo-create', theme),
        openRebuild: (theme) => openAction('rebuild', theme),
        openDelete: (theme) => openAction('delete', theme),
    };
}
