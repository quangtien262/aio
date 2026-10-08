<style>
    .rx13-page .xd-container { width: min(1480px, calc(100% - 48px)); margin-inline: auto; }
    .rx13-page .xd-page-main { overflow-wrap: anywhere; }
    .rx13-page .xd-page-main :is(.xd-product-hero, .xd-content-grid, .xd-detail, .xd-services-list) > * { min-width: 0; }
    .rx13-page :is(.xd-rich, .xd-rich-content) table { display: block; max-width: 100%; overflow-x: auto; }
    @media (max-width: 900px) {
        .rx13-page .xd-services-list { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 760px) {
        .rx13-page .xd-container { width: calc(100% - 32px); }
    }
    @media (max-width: 640px) {
        .rx13-page .xd-services-list { grid-template-columns: minmax(0, 1fr); gap: 24px; }
    }
</style>
