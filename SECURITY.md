# Security Policy

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

## Reporting a vulnerability

Please report security issues privately to **info@intragile.eu** rather than opening a public
issue. Include the affected version and, if possible, steps to reproduce. You will receive a
reply within a few working days.

## Scope

The package reads public data from the MNB web service and stores nothing except cached
responses in the Laravel cache store you configure. The MNB service is available over plain
HTTP only, so responses are not protected in transit; XML is parsed without network access to
external entities.

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu).
