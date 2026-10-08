<style>
    .rx13-contact { padding-top: 20px; scroll-margin-top: 24px; }
    .rx13-contact__shell { display: grid; grid-template-columns: minmax(0,.9fr) minmax(0,1.1fr); gap: 52px; align-items: start; padding: 52px; border-radius: 32px; background: var(--rx13-deep); }
    .rx13-contact__copy { padding: 12px 0; color: #fff; }
    .rx13-contact__eyebrow { margin: 0 0 18px; color: var(--rx13-green); font-size: 12px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
    .rx13-contact__copy h2 { max-width: 480px; margin: 0; color: #fff; font-size: clamp(30px,3.4vw,44px); font-weight: 800; line-height: 1.15; letter-spacing: -.025em; text-wrap: balance; }
    .rx13-contact__intro { max-width: 470px; margin: 22px 0 32px; color: #d5e6df; font-size: 16px; line-height: 1.75; }
    .rx13-contact__details { display: grid; gap: 20px; margin: 0; padding-top: 24px; border-top: 1px solid #ffffff26; }
    .rx13-contact__details dt { margin-bottom: 6px; color: #bad3c8; font-size: 12px; }
    .rx13-contact__details dd { margin: 0; color: #fff; font-size: 16px; font-weight: 600; line-height: 1.6; overflow-wrap: anywhere; }
    .rx13-contact__details a:hover { color: var(--rx13-green); }
    .rx13-contact__note { margin-top: 24px; padding-left: 16px; border-left: 2px solid var(--rx13-green); }
    .rx13-contact__note h3 { margin: 0 0 8px; font-size: 16px; }
    .rx13-contact__note p { margin: 0; color: #d5e6df; font-size: 14px; line-height: 1.7; }
    .rx13-contact__card { min-width: 0; padding: 32px; border-radius: 22px; background: #fff; color: var(--rx13-deep); }
    .rx13-contact__card h3 { margin: 0 0 8px; font-size: 23px; line-height: 1.3; }
    .rx13-contact__hint { margin: 0 0 24px; color: #627168; font-size: 12px; line-height: 1.6; }
    .rx13-contact__fields { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 18px 16px; }
    .rx13-contact__wide { grid-column: 1/-1; }
    .rx13-contact__fields label { display: block; margin-bottom: 7px; color: #24493a; font-size: 13px; font-weight: 600; }
    .rx13-contact__fields label span { color: #7c3630; }
    .rx13-contact__fields input,.rx13-contact__fields textarea { display: block; width: 100%; min-width: 0; padding: 11px 13px; border: 1px solid #d8e1db; border-radius: 8px; background: #fafcf9; color: #203c2e; font: inherit; font-size: 15px; line-height: 1.5; }
    .rx13-contact__fields textarea { min-height: 112px; resize: vertical; }
    .rx13-contact__fields input:focus,.rx13-contact__fields textarea:focus { border-color: var(--rx13-deep); background: #fff; }
    .rx13-contact__fields [aria-invalid="true"] { border-color: #b63730; }
    .rx13-contact__fields small { display: block; margin-top: 7px; color: #627168; font-size: 11px; line-height: 1.6; }
    .rx13-contact__form button { display: flex; width: 100%; min-height: 48px; align-items: center; justify-content: center; gap: 16px; margin-top: 22px; padding: 12px 20px; border: 0; border-radius: 999px; background: var(--rx13-green); color: var(--rx13-deep); font: inherit; font-size: 14px; font-weight: 800; cursor: pointer; }
    .rx13-contact__form button span { font-size: 20px; }
    .rx13-contact__form button:hover { background: #8be329; }
    .rx13-contact :focus-visible { outline: 3px solid #32865c; outline-offset: 3px; }
    .rx13-contact__copy :focus-visible { outline-color: var(--rx13-green); }
    .rx13-contact__notice,.rx13-contact__error { padding: 14px; margin-bottom: 20px; border-radius: 8px; font-size: 13px; line-height: 1.65; scroll-margin-top: 24px; }
    .rx13-contact__notice { color: #205c3c; background: #edf8ef; }
    .rx13-contact__error { color: #922f28; background: #fff1ef; }
    .rx13-contact__error ul { margin: 8px 0 0; padding-left: 18px; }
    @media(max-width:1000px) { .rx13-contact__shell { gap: 28px; padding: 32px; } .rx13-contact__card { padding: 24px; } }
    @media(max-width:760px) { .rx13-contact__shell { grid-template-columns: 1fr; gap: 28px; padding: 28px 20px; border-radius: 24px; } .rx13-contact__copy { padding: 0; } .rx13-contact__intro { margin: 18px 0 24px; font-size: 15px; } .rx13-contact__details { gap: 16px; padding-top: 20px; } .rx13-contact__card { padding: 24px 20px; border-radius: 16px; } }
    @media(max-width:480px) { .rx13-contact__fields { grid-template-columns: 1fr; gap: 16px; } .rx13-contact__card h3 { font-size: 21px; } .rx13-contact__fields input,.rx13-contact__fields textarea { font-size: 16px; } }
</style>
