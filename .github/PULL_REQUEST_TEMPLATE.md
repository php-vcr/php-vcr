| Q                | A
| ---------------- | ---
| Type             | Bug / Feature / Enhancement / Deprecation / BC Break / Security / Documentation / CI / Dependencies
| Fixes            | Fix #... <!-- one line per referenced issue; omit if no related issue exists -->
| BC break?        | yes / no <!-- yes is only mergeable into a major release -->
| Deprecation?     | yes / no <!-- if yes, document the replacement and removal target -->
| New dependency?  | yes / no <!-- if yes, justify and link to the discussion -->
| License          | MIT

<!--
🛠️ Replace this comment with a short explanation of the change:
- What it does and why it is needed
- Example (PHP snippet, cassette excerpt) when behaviour is user-visible
- Before/after for behavioural changes

Contributor guidelines:
- ✅ All checks green: PHPUnit + PHPStan + PHP-CS-Fixer + editorconfig
- 🐳 Verify locally on the Docker matrix — list the workspaces you actually ran
  (e.g. workspace80 lowest/highest, workspace84 lowest/highest)
- 📝 Conventional Commits (feat/fix/chore/refactor/docs/...), one logical change per commit, every commit green
- 🔒 No breaking changes outside of a major release
- 🏷️ Carry over the milestone and labels from the linked issue(s). Type labels:
  Bug, Feature, Enhancement, Deprecation, BC Break, Security, Documentation, CI, Dependencies
-->
