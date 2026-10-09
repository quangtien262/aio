@unless(request()->routeIs('site.home'))
<style data-storefront-inner-header>
    /* Overlay headers belong to the home hero. Inner pages reserve their full, responsive height. */
    html:root body :is(
        header.bds-header,
        header.dn-header,
        header.dn351-header,
        header.ec15-header,
        header.dr-header,
        header.xd4-header,
        header.xd5-header,
        header.xd12-header,
        header.af15-site-header,
        header.xd323-header,
        header.xd324-header
    ) {
        position: relative !important;
        inset: auto !important;
    }

    /* These home headers rely on the hero image for contrast. */
    html:root body header.bds-header { background: var(--bds-navy, #182a3c); }
    html:root body header.dn-header { background: var(--dn-navy-deep, #18191b); }
    html:root body header.ec15-header { background: #191715; }
    html:root body header.xd4-header { background: var(--xd4-ink, #202326); }
    html:root body header.af15-site-header { background: #151515; }
    html:root body header.xd323-header { background: var(--xd323-green-dark, #043d2f); }
    html:root body header.xd324-header { background: var(--wolf-dark, #142322); }
    html:root body header .xd3-nav-wrap,
    html:root body header .f406-header__shell { margin-bottom: 0 !important; }
    html:root body header .foot-navigation-wrap { height: auto; }
    html:root body header .foot-navigation {
        position: relative;
        inset: auto;
        transform: none;
        width: 100%;
    }
    html:root body header .f408-mainbar,
    html:root body header .x325-top,
    html:root body header .x325-nav {
        position: relative;
        inset: auto;
    }
    html:root body header .x325-top { background: var(--x325-black, #080808); }
    html:root body header .x325-nav { padding-block: 12px; }
</style>
@endunless
