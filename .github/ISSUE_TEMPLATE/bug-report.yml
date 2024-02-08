name: Bug Report
description: Tell me everything. Is the sky falling on your head, or is it just a bug in the code?
labels: [ '[Type] Bug' ]
body:
  - type: textarea
    id: steps
    attributes:
      label: Steps to Reproduce
      placeholder: |
        1. Go to '...'
        2. Click on '....'
        3. Scroll down to '....'
        ...
    validations:
      required: true
  - type: dropdown
    id: users-affected
    attributes:
      label: Severity
      description: How many users are impacted? A guess is fine.
      options:
        - One
        - Some (< 50%)
        - Most (> 50%)
        - All
  - type: dropdown
    id: workarounds
    attributes:
      label: Available workarounds?
      options:
        - No and the platform is unusable
        - No but the platform is still usable
        - Yes, difficult to implement
        - Yes, easy to implement
  - type: textarea
    id: workarounds-detail
    attributes:
      label: Workaround details
      description: If you are aware of a workaround, please describe it below.
      placeholder: |
        e.g. There is an alternative way to access this setting in the sidebar, but it's not readily apparent.
