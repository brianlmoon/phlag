---
applyTo: "**"
excludeAgent: "coding-agent"
---

When performing a pull-request code review, make every finding independently
actionable by another coding agent.

End each review comment with a section titled "Agent prompt" containing a
self-contained prompt that can be copied into another coding agent.

The prompt must:

- Tell the agent to verify the finding against the current code before changing anything.
- Tell the agent to fix only issues that are still valid.
- Tell the agent to skip invalid or outdated findings and briefly explain why.
- Include the relevant file path, approximate line or symbol, and the complete issue.
- Describe the expected correction without prescribing unnecessary implementation details.
- Request minimal, focused changes.
- Request appropriate tests, linting, type checking, or other relevant validation.

Use this general structure:

Agent prompt:

Verify this finding against the current code. Fix it only if it is still valid;
otherwise, make no change and briefly explain why. Keep the change minimal and
focused, and run the relevant validation.

In `<file>` near `<line or symbol>`, `<self-contained explanation of the issue
and expected correction>`.
