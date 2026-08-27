=== WP CBT Pro ===
Contributors: yourorg
Tags: exams, cbt, quiz, e-learning, proctoring
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A modular computer-based examination platform: candidate management, camera
proctoring, secure external code execution, and an interactive data
structures & algorithms engine.

== Description ==

WP CBT Pro is built for institutions running formal, auditable examinations
— schools, universities, polytechnics, professional bodies, and government
examiners. See ARCHITECTURE for the full design specification.

Question types, camera/identity verification providers, and code execution
backends are all pluggable interfaces rather than hard-coded branches.
Candidate code is never executed inside WordPress: programming submissions
are graded by a separate, sandboxed execution service reached only over
HTTPS.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/wp-cbt-pro`.
2. Run `composer install` inside the plugin directory (optional — a
   fallback autoloader is bundled for environments without Composer).
3. Activate through the "Plugins" screen in WordPress.

== Changelog ==

= 0.2.0 =
* Phase 2: plugin bootstrap, database schema/migrations, role & capability
  registration, REST wiring point.

= 0.1.0 =
* Phase 1: architecture and design specification.
