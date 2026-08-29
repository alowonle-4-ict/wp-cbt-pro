=== WP CBT Pro ===
Contributors: adigunnurudeen
Tags: exams, cbt, quiz, e-learning, proctoring
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 0.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A modular computer-based examination platform: candidate management, camera
proctoring, secure external code execution, and an interactive data
structures & algorithms engine.

== Description ==

WP CBT Pro is built for institutions running formal, auditable examinations
— schools, universities, polytechnics, professional bodies, and government
examiners.

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

= 0.5.0 =
* Candidate management with photo capture and institution scoping.
* Pluggable question types: multiple choice, programming (external sandboxed
  execution and grading), and interactive data structures & algorithms.
* Word/DOCX question import, including OMML-to-MathML conversion for
  mathematical content.
* Exam authoring with question randomization, pools, and negative marking.
* Exam runtime: attempts, answers, timed submission, and delayed/immediate
  results release.
* Camera-based proctoring: session tracking, identity verification review,
  and an invigilator monitoring dashboard.
* Results export and a GDPR-compliant personal data export/erase pipeline
  with scheduled retention cleanup.
* PHPUnit unit test suite for the pure-logic layer.

= 0.2.0 =
* Phase 2: plugin bootstrap, database schema/migrations, role & capability
  registration, REST wiring point.

= 0.1.0 =
* Phase 1: architecture and design specification.
