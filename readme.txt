=== Nalu AI ===
Contributors: naluservices
Tags: chatbot, ai, chat widget
Requires at least: 6.3
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds your Nalu AI chatbot to your WordPress site as a floating chat widget.

== Description ==

The plugin exchanges your agent's client credentials for a short-lived widget token on the
server, so the client secret never reaches visitors' browsers, and then loads the hosted Nalu
chat widget.

== Installation ==

1. In Nalu, open your agent and set its platform to **WordPress**. Make sure it is active and published.
2. Install and activate this plugin, then go to **Settings > Nalu AI**.
3. Enter the Nalu URL (for example `https://dreamit.naluservices.com`), the agent slug, and the client ID and secret from the agent's embed credentials.
4. Add the site origin shown on the settings page to the agent's Allowed Origins in Nalu.
5. Click **Test connection**, then tick **Enabled** and save.

To keep the client secret out of the database, define it in `wp-config.php` instead:

    define( 'NALU_AI_CLIENT_SECRET', 'your-client-secret' );

== Updates ==

From 2.0.0 on, new versions appear on the **Plugins** screen like any other plugin update, and
you can turn on automatic updates there. Updates come from this plugin's GitHub releases.

**Upgrading from 1.x:** 1.x can't update itself, so install 2.0.0 by hand once. Your settings are kept.

1. Deactivate and delete the old Nalu AI plugin.
2. Go to **Plugins > Add New > Upload Plugin** and upload `nalu-ai.zip` from the latest release.
3. Activate it and check **Settings > Nalu AI**.

== Frequently Asked Questions ==

= How do I show the chatbot on some pages only? =

Set Display mode to "Only pages with the shortcode" and add `[nalu_ai_chatbot]` to those pages.
The chatbot still floats in the corner. `[nalu_ai_chatbot width="420px" height="640px"]` overrides
its size on that page.

= How do I put the chat inside a page instead of floating? =

Add `[nalu_ai_chatbot mode="inline" height="640px"]` where the chat should appear, for example in a
Shortcode block in the middle of the page. It shows as part of the page, with no launcher button or
header, and fills the content column (`width` caps it). That page doesn't also get the floating chat.

= Can different pages use different agents? =

Yes. Add each extra agent under **Settings > Nalu AI > More agents** with its own slug, client ID,
and client secret from Nalu, and add this site to that agent's Allowed Origins in Nalu. Then use
`[nalu_ai_chatbot agent="parts-lead" mode="inline"]` on the page that should use it. Without `agent`,
the main agent is used. Test connection checks every agent.

= Does it work with page caching plugins? =

Yes. The page only contains a static script; the widget token is fetched from the uncached
`/wp-json/nalu-ai/v1/widget-config` endpoint.

== Changelog ==

= 2.1.0 =
* Inline chats: `[nalu_ai_chatbot mode="inline"]` shows the chat inside the page where the shortcode is placed.
* More agents: add extra agents with their own credentials and pick one per page with `agent="slug"`.
* A page with a chatbot shortcode no longer also gets the automatic floating chat.
* Test connection checks every configured agent.
* Each agent's widget token is cached separately.

= 2.0.0 =
* Updates from GitHub releases on the Plugins screen, including automatic updates.
* Settings page rebuilt on the WordPress Settings API, with a Test connection button and a Settings link on the Plugins screen.
* The saved client secret is no longer printed into the settings page; leave the field blank to keep it. It can also come from `NALU_AI_CLIENT_SECRET` in wp-config.php.
* Widget tokens are cached and shared across page views instead of being requested on every page view, and failures are cached for a minute.
* The public widget-config endpoint no longer returns internal URLs or Nalu's error responses; details go to the PHP error log and the Test connection result.
* An expired widget token no longer leaves a broken chat: the widget is refreshed when the token expires or the tab is reopened.
* The frontend script is enqueued and deferred instead of printed inline.
* Fixed the shortcode printing its script above the post content.
* Width and height must be valid CSS sizes.
* New installs start without a default Nalu URL. Existing settings are kept as they are.
* Deleting the plugin removes its settings.

= 1.2.0 =
* Cache-control headers on the widget-config endpoint.
