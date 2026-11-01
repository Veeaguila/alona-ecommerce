# Upstream source

Imported 30 October 2026 from https://github.com/Veeaguila/alona-mobile.git,
branch `main`, commit `427799595a4cb19b258c094a9d9c7dd826e9e1c4`.
All upstream tracked files were extracted as regular ALONA files. No nested `.git`
or submodule was imported. Future pulls of the ALONA branch include this source.

Upstream modules are Buyer and Logistics; no Seller Flutter module exists in this
revision. Rider remains the independent `../rider` application. Local changes adapt
Logistics to the existing ALONA workflow, not the other way around.

Added native secure storage using the same pinned major as Rider. The dependency
resolution retained existing locked packages and added only that plugin's platform
dependencies. No SDK or dependency upgrade was performed.

Figtree's seven normal weights are bundled so UI/tests need no runtime font network
access. Downloads came from the exact Google Fonts URLs/hash values in locked
google_fonts9.0.0; each SHA-256 was verified. License: `assets/fonts/OFL.txt`.
Other platform/assets/templates retain upstream provenance; release signing and
device packaging require separate verification.
