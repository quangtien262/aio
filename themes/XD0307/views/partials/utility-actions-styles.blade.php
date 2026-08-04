<style>
    .xd5-utility > .xd5-container {
        align-items: center;
        justify-content: space-between;
        gap: 24px;
        padding: 8px 0;
    }
    .xd5-utility-contact,
    .xd5-utility-actions {
        display: flex;
        align-items: center;
        gap: 24px;
    }
    .xd5-utility-contact {
        min-width: 0;
        flex: 1;
        justify-content: flex-end;
    }
    .xd5-utility-actions {
        flex: 0 0 auto;
        gap: 10px;
    }
    .xd5-auth-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 0;
        background: transparent;
        color: #fff;
        font: inherit;
        font-weight: 800;
        line-height: 1;
        cursor: pointer;
    }
    .xd5-auth-link:hover,
    .xd5-auth-link:focus-visible {
        color: var(--gold);
        outline: 0;
    }
    .xd5-auth-separator {
        opacity: .6;
    }
    .xd5-language {
        display: flex;
        align-items: center;
    }
    .xd5-language .sf-language-switcher {
        position: static !important;
        z-index: auto;
        font: inherit;
        color: #fff;
    }
    .xd5-language .sf-language-switcher summary {
        min-height: 28px;
        padding: 4px 8px;
        border-color: #ffffff40;
        background: #ffffff17;
        color: #fff;
        box-shadow: none;
        backdrop-filter: none;
    }
    .xd5-language .sf-language-switcher__menu {
        z-index: 30;
        color: #172033;
    }
    .xd5-language .sf-language-switcher__menu a {
        color: #334155 !important;
    }
    @media(max-width: 900px) {
        .xd5-utility-contact {
            gap: 14px;
        }
    }
    @media(max-width: 620px) {
        .xd5-utility {
            display: block;
        }
        .xd5-utility > .xd5-container {
            align-items: flex-start;
            flex-direction: column;
            padding: 10px 0;
        }
        .xd5-utility-contact,
        .xd5-utility-actions {
            width: 100%;
            justify-content: flex-start;
            flex-wrap: wrap;
            gap: 10px 14px;
        }
    }
</style>
