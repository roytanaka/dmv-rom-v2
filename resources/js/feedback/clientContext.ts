// The client half of a Feedback item's context (#676, ADR-0029 §10). The browser sends
// only what the server cannot know: the page URL, the user agent, and the viewport size.
// The server fills the rest (route, locale, Member, impersonator, version, time).
//
// Pure, so it takes its window, navigator, and location as input. The caps match the
// server's validation, so a long URL or user agent never blocks a send.

export interface ClientContextSource {
    location: { pathname: string; search: string };
    navigator: { userAgent: string };
    innerWidth: number;
    innerHeight: number;
}

export interface ClientContext {
    page_url: string;
    user_agent: string;
    viewport_width: number;
    viewport_height: number;
}

const PAGE_URL_MAX = 2048;
const USER_AGENT_MAX = 512;

export function buildClientContext(source: ClientContextSource): ClientContext {
    return {
        page_url: `${source.location.pathname}${source.location.search}`.slice(0, PAGE_URL_MAX),
        user_agent: source.navigator.userAgent.slice(0, USER_AGENT_MAX),
        viewport_width: Math.round(source.innerWidth),
        viewport_height: Math.round(source.innerHeight),
    };
}
