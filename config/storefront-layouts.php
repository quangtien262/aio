<?php

// Shared header/content geometry. Keep breakpoint widths together when changing a theme shell.
return [
    'AUTO850' => [
        'content_container' => '.a850-container',
        'container' => '.a850-wrap',
        'width' => 'min(calc(100% - 40px),1680px)',
        'breakpoints' => [
            '(max-width:640px)' => 'calc(100% - 24px)',
        ],
    ],
    'AUTO851' => [
        'content_container' => '.a851-container',
        'container' => '.a851-wrap',
        'width' => 'min(calc(100% - 32px),1440px)',
        'breakpoints' => [
            '(max-width:700px)' => 'min(calc(100% - 24px),1440px)',
        ],
    ],
    'AUTO852' => [
        'content_container' => '.a852-container',
        'container' => '.a852-wrap',
        'width' => 'min(calc(100% - 40px),1500px)',
        'breakpoints' => [
            '(max-width:760px)' => 'min(calc(100% - 24px),1500px)',
        ],
    ],
    'AUTO853' => [
        'content_container' => '.a853-container',
        'container' => '.a853-wrap',
        'width' => 'min(calc(100% - 48px),1680px)',
        'breakpoints' => [
            '(max-width:760px)' => 'min(calc(100% - 24px),1680px)',
        ],
    ],
    'BDS701' => [
        'container' => '.bds-container',
        'width' => 'min(1180px,calc(100% - 40px))',
        'breakpoints' => [
            '(max-width:620px)' => 'min(100% - 24px,1180px)',
        ],
    ],
    'BDS702' => [
        'content_container' => '.bds-container',
        'container' => '.b702-container',
        'width' => 'min(1420px,calc(100% - 48px))',
        'breakpoints' => [
            '(max-width:640px)' => 'min(100% - 28px,1420px)',
        ],
    ],
    'BOOK920' => [
        'container' => '.book20-container',
        'width' => 'min(1600px,calc(100% - 48px))',
        'breakpoints' => [
            '(max-width:900px)' => 'min(100% - 28px,1600px)',
        ],
    ],
    'BZ501' => [
        'container' => '.bz501-container',
        'width' => 'var(--bz-container)',
    ],
    'CA0050' => [
        'content_container' => '.ca50-wrap, .ca50-inner-container, .ca50-detail-container, .ca50-contact-container, .ca50-container',
        'container' => '.ca50-header-inner',
        'width' => 'min(var(--ca-width),calc(100% - 100px))',
        'breakpoints' => [
            '(max-width:640px)' => 'calc(100% - 24px)',
        ],
    ],
    'DL750' => [
        'container' => '.dl-wrap',
        'width' => 'min(1180px,calc(100% - 40px))',
        'breakpoints' => [
            '(max-width:640px)' => 'min(100% - 28px,1180px)',
        ],
    ],
    'DN202' => [
        'container' => '.d202-container',
        'width' => 'min(1480px,calc(100% - 48px))',
        'breakpoints' => [
            '(max-width:780px)' => 'calc(100% - 28px)',
        ],
    ],
    'DN302' => [
        'container' => '.dn-container',
        'width' => 'min(1680px,calc(100% - 64px))',
        'breakpoints' => [
            '(max-width:1200px)' => 'min(100% - 40px,1120px)',
            '(max-width:900px)' => 'min(100% - 30px,760px)',
            '(max-width:600px)' => 'calc(100% - 28px)',
        ],
    ],
    'DN350' => [
        'content_container' => '.dn-container',
        'container' => '.dn350-container',
        'width' => 'min(1760px,calc(100% - 64px))',
        'breakpoints' => [
            '(max-width:760px)' => 'min(100% - 32px,1760px)',
        ],
    ],
    'DN351' => [
        'content_container' => '.dn-container',
        'container' => '.dn351-container',
        'width' => 'min(1760px,calc(100% - 64px))',
        'breakpoints' => [
            '(max-width:760px)' => 'min(100% - 32px,1760px)',
        ],
    ],
    'E800' => [
        'container' => '.e800-container',
        'width' => 'min(1840px,calc(100% - 80px))',
        'breakpoints' => [
            '(max-width:960px)' => 'min(100% - 32px,1840px)',
            '(max-width:640px)' => 'min(100% - 24px,1840px)',
        ],
    ],
    'E801' => [
        'container' => '.e801-container',
        'width' => 'min(1830px,calc(100% - 72px))',
        'breakpoints' => [
            '(max-width:900px)' => 'min(100% - 30px,1830px)',
            '(max-width:600px)' => 'min(100% - 20px,1830px)',
        ],
    ],
    'E802' => [
        'container' => '.e802-container',
        'width' => 'min(1840px,calc(100% - 72px))',
        'breakpoints' => [
            '(max-width:1250px)' => 'min(100% - 40px,1840px)',
            '(max-width:900px)' => 'min(100% - 28px,1840px)',
            '(max-width:600px)' => 'min(100% - 20px,1840px)',
        ],
    ],
    'E803' => [
        'container' => '.e803-container',
        'width' => 'min(1780px,calc(100% - 56px))',
        'breakpoints' => [
            '(max-width:980px)' => 'min(100% - 28px,1780px)',
            '(max-width:640px)' => 'min(100% - 18px,1780px)',
        ],
    ],
    'E804' => [
        'container' => '.e804-container',
        'width' => 'min(1760px,calc(100% - 40px))',
        'breakpoints' => [
            '(max-width:720px)' => 'min(100% - 24px,680px)',
        ],
    ],
    'E805' => [
        'container' => '.e805-container',
        'width' => 'min(1800px,calc(100% - 48px))',
        'breakpoints' => [
            '(max-width:720px)' => 'calc(100% - 24px)',
        ],
    ],
    'E806' => [
        'container' => '.e806-container',
        'width' => 'min(1760px,calc(100% - 48px))',
        'breakpoints' => [
            '(max-width:820px)' => 'calc(100% - 24px)',
        ],
    ],
    'E807' => [
        'container' => '.e807-container',
        'width' => 'min(1180px,calc(100% - 32px))',
        'breakpoints' => [
            '(max-width:760px)' => 'min(100% - 24px,1180px)',
        ],
    ],
    'EC900' => [
        'container' => '.ec9-container',
        'width' => 'min(1780px,calc(100% - 40px))',
        'breakpoints' => [
            '(max-width:980px)' => 'min(100% - 28px,1780px)',
            '(max-width:640px)' => 'min(100% - 20px,1780px)',
        ],
    ],
    'EC901' => [
        'container' => '.ec91-container',
        'width' => 'min(1720px,calc(100% - 48px))',
        'breakpoints' => [
            '(max-width:900px)' => 'min(100% - 28px,1720px)',
            '(max-width:600px)' => 'min(100% - 20px,1720px)',
        ],
    ],
    'EC902' => [
        'container' => '.ec92-container',
        'width' => 'min(1840px,calc(100% - 46px))',
        'breakpoints' => [
            '(max-width:900px)' => 'min(100% - 24px,1840px)',
            '(max-width:600px)' => 'min(100% - 16px,1840px)',
        ],
    ],
    'EC903' => [
        'container' => '.ec93-container',
        'width' => 'min(1600px,calc(100% - 42px))',
        'breakpoints' => [
            '(max-width:800px)' => 'min(100% - 20px,1600px)',
        ],
    ],
    'EC904' => [
        'container' => '.ec94-container',
        'width' => 'min(1760px,calc(100% - 72px))',
        'breakpoints' => [
            '(max-width:850px)' => 'calc(100% - 24px)',
        ],
    ],
    'EC905' => [
        'container' => '.ec95-container',
        'width' => 'min(1760px,calc(100% - 64px))',
        'breakpoints' => [
            '(max-width:980px)' => 'calc(100% - 28px)',
        ],
    ],
    'EC906' => [
        'content_container' => '.ec96-container, .ec96-article-wrap',
        'container' => '.ec96-container',
        'width' => 'min(1820px,calc(100% - 64px))',
        'breakpoints' => [
            '(max-width:1200px)' => 'min(100% - 36px,1160px)',
            '(max-width:800px)' => 'calc(100% - 28px)',
        ],
    ],
    'EC907' => [
        'container' => '.ec97-container',
        'width' => 'min(1900px,calc(100% - 80px))',
        'breakpoints' => [
            '(max-width:1400px)' => 'calc(100% - 36px)',
            '(max-width:800px)' => 'calc(100% - 24px)',
        ],
    ],
    'EC908' => [
        'container' => '.ec98-container',
        'width' => 'min(1870px,calc(100% - 90px))',
        'breakpoints' => [
            '(max-width:1450px)' => 'calc(100% - 40px)',
            '(max-width:700px)' => 'calc(100% - 24px)',
        ],
    ],
    'EC909' => [
        'content_container' => '.ec99-container',
        'container' => '.ec99-shell',
        'width' => 'min(2020px,calc(100% - 28px))',
        'breakpoints' => [
            '(max-width:720px)' => 'calc(100% - 20px)',
        ],
    ],
    'EC910' => [
        'container' => '.ec10-container',
        'width' => 'min(1840px,calc(100% - 72px))',
        'breakpoints' => [
            '(max-width:900px)' => 'min(100% - 28px,1840px)',
            '(max-width:600px)' => 'min(100% - 18px,1840px)',
        ],
    ],
    'EC911' => [
        'content_container' => '.ec97-container',
        'container' => '.ec11-container',
        'width' => 'min(calc(100% - 48px),var(--ec11-container))',
        'breakpoints' => [
            '(max-width:680px)' => 'min(calc(100% - 28px),var(--ec11-container))',
        ],
    ],
    'EC912' => [
        'container' => '.ec12-container',
        'width' => 'min(calc(100% - 48px),var(--ec12-container))',
        'breakpoints' => [
            '(max-width:900px)' => 'min(calc(100% - 28px),var(--ec12-container))',
        ],
    ],
    'EC913' => [
        'container' => '.ec13-container',
        'width' => 'min(1440px,calc(100% - 40px))',
        'breakpoints' => [
            '(max-width:820px)' => 'min(720px,calc(100% - 28px))',
            '(max-width:520px)' => 'calc(100% - 20px)',
        ],
    ],
    'EC914' => [
        'container' => '.ec14-container',
        'width' => 'min(1540px,calc(100% - 40px))',
        'breakpoints' => [
            '(max-width:820px)' => 'min(100% - 28px,1540px)',
        ],
    ],
    'EC915' => [
        'container' => '.ec15-container',
        'width' => 'min(1540px,calc(100% - 44px))',
        'breakpoints' => [
            '(max-width:820px)' => 'calc(100% - 28px)',
        ],
    ],
    'EC916' => [
        'container' => '.ec16-container',
        'width' => 'min(1500px,calc(100% - 40px))',
    ],
    'EC917' => [
        'container' => '.ec17-container',
        'width' => 'min(calc(100% - 48px),var(--ec17-container))',
        'breakpoints' => [
            '(max-width:980px)' => 'min(calc(100% - 30px),var(--ec17-container))',
        ],
    ],
    'FOOT401' => [
        'container' => '.foot-container',
        'width' => 'min(1180px,calc(100% - 40px))',
        'breakpoints' => [
            '(max-width:640px)' => 'min(100% - 28px,1180px)',
        ],
    ],
    'FOOT403' => [
        'container' => '.dr-container',
        'width' => 'min(1180px,calc(100% - 40px))',
        'breakpoints' => [
            '(max-width:720px)' => 'min(100% - 28px,1180px)',
        ],
    ],
    'FOOT404' => [
        'container' => '.f404-container',
        'width' => 'min(1460px,calc(100% - 48px))',
        'breakpoints' => [
            '(max-width:900px)' => 'min(100% - 30px,720px)',
            '(max-width:560px)' => 'min(100% - 24px,520px)',
        ],
    ],
    'FOOT405' => [
        'container' => '.f405-container',
        'width' => 'min(1180px,calc(100% - 40px))',
        'breakpoints' => [
            '(max-width:780px)' => 'min(100% - 28px,1180px)',
        ],
    ],
    'FOOT406' => [
        'container' => '.f406-container',
        'width' => 'min(1180px,calc(100% - 40px))',
        'breakpoints' => [
            '(max-width:760px)' => 'min(100% - 28px,1180px)',
        ],
    ],
    'FOOT407' => [
        'container' => '.f407-container',
        'width' => 'min(1180px,calc(100% - 40px))',
        'breakpoints' => [
            '(max-width:760px)' => 'min(100% - 28px,1180px)',
        ],
    ],
    'FOOT408' => [
        'container' => '.f408-container',
        'width' => 'min(1220px,calc(100% - 40px))',
        'breakpoints' => [
            '(max-width:760px)' => 'min(100% - 28px,1220px)',
        ],
    ],
    'FOOT409' => [
        'container' => '.f409-container',
        'width' => 'min(1480px,calc(100% - 48px))',
        'breakpoints' => [
            '(max-width:720px)' => 'min(100% - 28px,1480px)',
        ],
    ],
    'NEWS88' => [
        'content_container' => '.n88-article',
        'container' => '.n88-container',
        'width' => 'min(1360px,calc(100% - 48px))',
        'breakpoints' => [
            '(max-width:620px)' => 'min(100% - 28px,1360px)',
        ],
    ],
    'NT501' => [
        'content_container' => '.nt-container, .nt-project-container, .nt-news-container',
        'container' => '.foot-container',
        'width' => 'min(1180px,calc(100% - 48px))',
        'breakpoints' => [
            '(max-width:600px)' => 'calc(100% - 32px)',
        ],
    ],
    'NT502' => [
        'container' => '.n502-container',
        'width' => 'min(1740px,calc(100% - 80px))',
        'breakpoints' => [
            '(max-width:850px)' => 'min(100% - 30px,1740px)',
            '(max-width:550px)' => 'min(100% - 22px,1740px)',
        ],
    ],
    'NT503' => [
        'container' => '.n503-container',
        'width' => 'min(1180px,calc(100% - 32px))',
        'breakpoints' => [
            '(max-width:560px)' => 'min(100% - 22px,1180px)',
        ],
    ],
    'NT504' => [
        'container' => '.n504-container',
        'width' => 'min(1870px,calc(100% - 80px))',
        'breakpoints' => [
            '(max-width:1450px)' => 'min(1320px,calc(100% - 48px))',
            '(max-width:760px)' => 'min(100% - 28px,1320px)',
        ],
    ],
    'SER0101' => [
        'container' => '.wrap',
        'width' => 'min(1180px, calc(100% - 24px))',
        'breakpoints' => [
            '(max-width:680px)' => 'calc(100% - 16px)',
        ],
    ],
    'SER102' => [
        'container' => '.ser102-container',
        'width' => 'min(1420px,calc(100% - 48px))',
        'breakpoints' => [
            '(max-width:640px)' => 'min(100% - 28px,1420px)',
        ],
    ],
    'SER103' => [
        'container' => '.ser103-container',
        'width' => 'min(1540px,calc(100% - 64px))',
        'breakpoints' => [
            '(max-width:900px)' => 'min(100% - 36px,1540px)',
        ],
    ],
    'SHOP601' => [
        'container' => '.s601-container',
        'width' => 'min(1840px,calc(100% - 64px))',
        'breakpoints' => [
            '(max-width:900px)' => 'min(100% - 32px,1840px)',
            '(max-width:600px)' => 'min(100% - 24px,1840px)',
        ],
    ],
    'SHOP602' => [
        'container' => '.s602-container',
        'width' => 'min(1880px,calc(100% - 80px))',
        'breakpoints' => [
            '(max-width:900px)' => 'min(100% - 32px,1880px)',
            '(max-width:600px)' => 'min(100% - 24px,1880px)',
        ],
    ],
    'SHOP603' => [
        'container' => '.s603-container',
        'width' => 'min(1690px,calc(100% - 72px))',
        'breakpoints' => [
            '(max-width:920px)' => 'min(100% - 32px,1690px)',
            '(max-width:620px)' => 'min(100% - 22px,1690px)',
        ],
    ],
    'SHOP604' => [
        'content_container' => '.s604-container',
        'container' => '.s604-header-inner',
        'width' => 'min(1980px,calc(100% - 54px))',
        'breakpoints' => [
            '(max-width:620px)' => 'calc(100% - 24px)',
        ],
    ],
    'SHOP605' => [
        'content_container' => '.s605-container',
        'container' => '.s605-header',
        'width' => 'calc(100% - 96px)',
        'breakpoints' => [
            '(max-width:640px)' => 'calc(100% - 64px)',
        ],
    ],
    'SHOP606' => [
        'content_container' => '.s606-container, .s606-service-wrap',
        'container' => '.s606-header-inner',
        'width' => 'min(1640px,calc(100% - 48px))',
        'breakpoints' => [
            '(max-width:760px)' => 'calc(100% - 28px)',
        ],
    ],
    'SPA111' => [
        'container' => '.sp11-container',
        'width' => 'min(1640px,calc(100% - 56px))',
        'breakpoints' => [
            '(max-width:1400px)' => 'min(1220px,calc(100% - 36px))',
            '(max-width:700px)' => 'calc(100% - 24px)',
        ],
    ],
    'SPA502' => [
        'container' => '.spa502-container',
        'width' => 'var(--spa-container)',
    ],
    'TH0050' => [
        'container' => '.th5-container',
        'width' => 'min(100% - 40px,1560px)',
        'breakpoints' => [
            '(max-width:900px)' => 'min(100% - 28px,1560px)',
        ],
    ],
    'TOOL750' => [
        'container' => '.t750-container',
        'width' => 'min(1680px,calc(100% - 64px))',
        'breakpoints' => [
            '(max-width:1024px)' => 'min(100% - 40px,1680px)',
            '(max-width:767px)' => 'calc(100% - 28px)',
        ],
    ],
    'TOOL751' => [
        'container' => '.t751-container',
        'width' => 'min(100% - 48px,1720px)',
        'breakpoints' => [
            '(max-width:1024px)' => 'min(100% - 32px,1720px)',
            '(max-width:430px)' => 'calc(100% - 24px)',
        ],
    ],
    'XD0301' => [
        'container' => '.xd-container',
        'width' => 'min(1540px,calc(100% - 56px))',
        'breakpoints' => [
            '(max-width:760px)' => 'min(100% - 28px,1540px)',
            '(max-width:640px)' => 'min(100% - 24px,1540px)',
            '(max-width:380px)' => 'min(100% - 18px,1540px)',
        ],
    ],
    'XD0302' => [
        'container' => '.xd2-container',
        'width' => 'var(--xd2-container)',
    ],
    'XD0303' => [
        'container' => '.xd3-container',
        'width' => 'var(--xd3-container)',
    ],
    'XD0304' => [
        'container' => '.xd4-container',
        'width' => 'var(--xd4-container)',
    ],
    'XD0305' => [
        'container' => '.xd5-container',
        'width' => 'var(--wide)',
    ],
    'XD0306' => [
        'content_container' => '.xd6-projects-container',
        'container' => '.xd5-container',
        'width' => 'var(--wide)',
    ],
    'XD0307' => [
        'container' => '.xd5-container',
        'width' => 'var(--wide)',
    ],
    'XD0308' => [
        'container' => '.xd4-container',
        'width' => 'var(--xd4-container)',
    ],
    'XD0309' => [
        'container' => '.xd5-container',
        'width' => 'var(--wide)',
    ],
    'XD0310' => [
        'container' => '.xd5-container',
        'width' => 'var(--wide)',
    ],
    'XD0311' => [
        'container' => '.xd5-container',
        'width' => 'var(--wide)',
    ],
    'XD0312' => [
        'container' => '.xd5-container',
        'width' => 'var(--wide)',
    ],
    'XD0313' => [
        'content_container' => '.rx13-header__inner, .rx13-container',
        'container' => '.rx13-container',
        'width' => 'min(1480px, calc(100% - 48px))',
        'breakpoints' => [
            '(max-width: 760px)' => 'min(100% - 32px, 1480px)',
        ],
    ],
    'XD0314' => [
        'container' => '.bb14-container',
        'width' => 'min(1480px, calc(100% - 48px))',
        'breakpoints' => [
            '(max-width: 760px)' => 'min(100% - 32px, 1480px)',
        ],
    ],
    'XD0315' => [
        'content_container' => '.af15-site-header__inner, .af15-container',
        'container' => '.af15-container',
        'width' => 'min(1420px, calc(100% - 48px))',
        'breakpoints' => [
            '(max-width: 760px)' => 'min(100% - 32px, 1420px)',
        ],
    ],
    'XD0318' => [
        'container' => '.fg18-container',
        'width' => 'min(1440px, calc(100% - 48px))',
        'breakpoints' => [
            '(max-width: 760px)' => 'min(100% - 32px, 1440px)',
        ],
    ],
    'XD0320' => [
        'container' => '.foot-container',
        'width' => 'min(1240px,calc(100% - 48px))',
        'breakpoints' => [
            '(max-width:760px)' => 'min(100% - 32px,1240px)',
            '(max-width:560px)' => 'calc(100% - 28px)',
        ],
    ],
    'XD0322' => [
        'container' => '.foot-container',
        'width' => 'min(1220px,calc(100% - 44px))',
        'breakpoints' => [
            '(max-width:560px)' => 'min(100% - 24px,1220px)',
        ],
    ],
    'XD0323' => [
        'container' => '.xd323-container',
        'width' => 'min(1560px,calc(100% - 48px))',
        'breakpoints' => [
            '(max-width:760px)' => 'min(680px,calc(100% - 28px))',
        ],
    ],
    'XD0324' => [
        'container' => '.xd324-container',
        'width' => 'min(1660px,calc(100% - 64px))',
        'breakpoints' => [
            '(max-width:820px)' => 'min(100% - 28px,720px)',
        ],
    ],
    'XD0325' => [
        'content_container' => '.xd-content-shell > .xd-container',
        'container' => '.x325-container',
        'width' => 'min(1580px,calc(100% - 56px))',
        'breakpoints' => [
            '(max-width:620px)' => 'calc(100% - 24px)',
        ],
    ],
    'XD321' => [
        'container' => '.foot-container',
        'width' => 'min(1240px,calc(100% - 40px))',
        'breakpoints' => [
            '(max-width:560px)' => 'min(100% - 30px,1240px)',
        ],
    ],
    'CORPORATE-STARTER' => [
        'content_container' => '.wrap, .site-main, .site-header',
        'container' => '.wrap',
        'width' => 'min(1180px,calc(100% - 24px))',
        'breakpoints' => ['(max-width:680px)' => 'calc(100% - 16px)'],
    ],
];
