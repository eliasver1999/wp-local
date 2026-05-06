const pptxgen = require("pptxgenjs");
const pres = new pptxgen();
pres.layout = "LAYOUT_16x9";
pres.title = "Personalized Cards Plugin - User Manual";

// ── Palette ────────────────────────────────────────────────────────────────────
const NAVY    = "1A2456";
const TEAL    = "028090";
const ICE     = "C8DFF0";
const WHITE   = "FFFFFF";
const LIGHT   = "F4F7FB";
const DARK    = "1A2456";
const MUTED   = "64748B";
const GRAY    = "E2E8F0";
const ORANGE  = "F47C3C";

const makeShadow = () => ({ type: "outer", blur: 8, offset: 3, angle: 135, color: "000000", opacity: 0.12 });

// ── Helpers ────────────────────────────────────────────────────────────────────
function darkSlide(slide) {
  slide.background = { color: NAVY };
}

function lightSlide(slide) {
  slide.background = { color: LIGHT };
}

function addHeader(slide, title, subtitle) {
  // Top accent bar
  slide.addShape(pres.shapes.RECTANGLE, { x: 0, y: 0, w: 10, h: 0.08, fill: { color: TEAL }, line: { color: TEAL } });
  // Title
  slide.addText(title, {
    x: 0.5, y: 0.18, w: 9, h: 0.65,
    fontSize: 26, bold: true, color: DARK, fontFace: "Calibri", margin: 0,
  });
  if (subtitle) {
    slide.addText(subtitle, {
      x: 0.5, y: 0.85, w: 9, h: 0.35,
      fontSize: 13, color: TEAL, fontFace: "Calibri", margin: 0, italic: true,
    });
  }
  // Divider line
  slide.addShape(pres.shapes.LINE, { x: 0.5, y: 1.2, w: 9, h: 0, line: { color: GRAY, width: 1.5 } });
}

function card(slide, x, y, w, h, title, body, accentColor) {
  const col = accentColor || TEAL;
  slide.addShape(pres.shapes.RECTANGLE, { x, y, w, h, fill: { color: WHITE }, line: { color: GRAY, width: 1 }, shadow: makeShadow() });
  slide.addShape(pres.shapes.RECTANGLE, { x, y, w: 0.07, h, fill: { color: col }, line: { color: col } });
  slide.addText(title, { x: x + 0.18, y: y + 0.12, w: w - 0.25, h: 0.35, fontSize: 13, bold: true, color: DARK, fontFace: "Calibri", margin: 0 });
  slide.addText(body, { x: x + 0.18, y: y + 0.48, w: w - 0.28, h: h - 0.6, fontSize: 11, color: MUTED, fontFace: "Calibri", margin: 0 });
}

function badge(slide, x, y, text, color) {
  const col = color || TEAL;
  slide.addShape(pres.shapes.RECTANGLE, { x, y, w: 1.0, h: 0.28, fill: { color: col }, line: { color: col }, rectRadius: 0.05 });
  slide.addText(text, { x, y, w: 1.0, h: 0.28, fontSize: 10, bold: true, color: WHITE, align: "center", valign: "middle", fontFace: "Calibri", margin: 0 });
}

function step(slide, x, y, num, title, desc) {
  slide.addShape(pres.shapes.OVAL, { x, y, w: 0.42, h: 0.42, fill: { color: TEAL }, line: { color: TEAL } });
  slide.addText(String(num), { x, y, w: 0.42, h: 0.42, fontSize: 14, bold: true, color: WHITE, align: "center", valign: "middle", fontFace: "Calibri", margin: 0 });
  slide.addText(title, { x: x + 0.52, y: y + 0.01, w: 3.5, h: 0.25, fontSize: 12, bold: true, color: DARK, fontFace: "Calibri", margin: 0 });
  slide.addText(desc, { x: x + 0.52, y: y + 0.26, w: 3.5, h: 0.28, fontSize: 10.5, color: MUTED, fontFace: "Calibri", margin: 0 });
}

// ══════════════════════════════════════════════════════════════════════════════
// SLIDE 1 — Title
// ══════════════════════════════════════════════════════════════════════════════
{
  const s = pres.addSlide();
  darkSlide(s);

  // Left accent column
  s.addShape(pres.shapes.RECTANGLE, { x: 0, y: 0, w: 0.22, h: 5.625, fill: { color: TEAL }, line: { color: TEAL } });

  // Big title
  s.addText("Personalized Cards", {
    x: 0.55, y: 1.4, w: 8.5, h: 1.1,
    fontSize: 52, bold: true, color: WHITE, fontFace: "Calibri", margin: 0,
  });
  s.addText("Plugin", {
    x: 0.55, y: 2.45, w: 8.5, h: 0.9,
    fontSize: 52, bold: true, color: ICE, fontFace: "Calibri", margin: 0,
  });

  // Subtitle
  s.addText("Complete User Manual  ·  Administrator Guide", {
    x: 0.55, y: 3.5, w: 8.5, h: 0.4,
    fontSize: 16, color: "A0B8D0", fontFace: "Calibri", margin: 0,
  });

  // Bottom bar
  s.addShape(pres.shapes.RECTANGLE, { x: 0, y: 5.2, w: 10, h: 0.425, fill: { color: "0F1A40" }, line: { color: "0F1A40" } });
  s.addText("wordpress plugin  ·  membership cards  ·  digital delivery", {
    x: 0.3, y: 5.21, w: 9.4, h: 0.4,
    fontSize: 10, color: "6B88A8", fontFace: "Calibri", align: "center", margin: 0,
  });
}

// ══════════════════════════════════════════════════════════════════════════════
// SLIDE 2 — Table of Contents
// ══════════════════════════════════════════════════════════════════════════════
{
  const s = pres.addSlide();
  lightSlide(s);
  addHeader(s, "What's Covered in This Manual", "");

  const sections = [
    { num: "01", title: "Initial Setup",     desc: "Upload templates, fonts & configure the plugin" },
    { num: "02", title: "Settings",          desc: "Text overlay, email, wallet & QR configuration" },
    { num: "03", title: "Managing Members",  desc: "User profiles, subscriptions & CSV bulk import" },
    { num: "04", title: "Creating Cards",    desc: "Single cards, bulk actions & editing" },
    { num: "05", title: "Member Frontend",   desc: "What members see on the My Card page" },
    { num: "06", title: "Emails",            desc: "Card delivery, renewal & 30-day reminders" },
    { num: "07", title: "Activity Log",      desc: "Track every action in the system" },
    { num: "08", title: "Settings Backup",   desc: "Export & import plugin configuration" },
  ];

  const cols = [[0,1,2,3],[4,5,6,7]];
  cols.forEach((idxArr, col) => {
    idxArr.forEach((i, row) => {
      const sec = sections[i];
      const x = 0.5 + col * 4.75;
      const y = 1.35 + row * 1.0;
      s.addShape(pres.shapes.RECTANGLE, { x, y, w: 4.4, h: 0.82, fill: { color: WHITE }, line: { color: GRAY, width: 1 }, shadow: makeShadow() });
      s.addShape(pres.shapes.RECTANGLE, { x, y, w: 0.06, h: 0.82, fill: { color: TEAL }, line: { color: TEAL } });
      s.addText(sec.num, { x: x + 0.15, y: y + 0.06, w: 0.5, h: 0.3, fontSize: 14, bold: true, color: TEAL, fontFace: "Calibri", margin: 0 });
      s.addText(sec.title, { x: x + 0.65, y: y + 0.06, w: 3.65, h: 0.3, fontSize: 13, bold: true, color: DARK, fontFace: "Calibri", margin: 0 });
      s.addText(sec.desc, { x: x + 0.15, y: y + 0.42, w: 4.1, h: 0.3, fontSize: 10.5, color: MUTED, fontFace: "Calibri", margin: 0 });
    });
  });
}

// ══════════════════════════════════════════════════════════════════════════════
// SLIDE 3 — Initial Setup
// ══════════════════════════════════════════════════════════════════════════════
{
  const s = pres.addSlide();
  lightSlide(s);
  addHeader(s, "01 — Initial Setup", "First steps after activating the plugin");

  // Section label
  s.addText("WHAT YOU NEED BEFORE CREATING CARDS", {
    x: 0.5, y: 1.35, w: 9, h: 0.28,
    fontSize: 9.5, bold: true, color: TEAL, charSpacing: 2, fontFace: "Calibri", margin: 0,
  });

  const items = [
    { title: "Front Card Template (JPG)", body: "Go to Settings → Upload Card Template.\nUpload a blank JPG card design. Text and photos will be overlaid on top.", color: TEAL },
    { title: "Back Card Template (JPG)", body: "Go to Settings → Upload Card Back Template.\nOptional. No text is printed on the back — it's used as-is.", color: ORANGE },
    { title: "Font File (TTF)", body: "Go to Settings → Upload Font.\nUpload a TrueType font (.ttf). This font is used for all text on the card.", color: NAVY },
  ];

  items.forEach((item, i) => {
    card(s, 0.5 + i * 3.08, 1.72, 2.9, 1.5, item.title, item.body, item.color);
  });

  // Note box
  s.addShape(pres.shapes.RECTANGLE, { x: 0.5, y: 3.42, w: 9, h: 1.88, fill: { color: "EBF5FB" }, line: { color: ICE, width: 1 } });
  s.addText("After Upload", { x: 0.75, y: 3.55, w: 4, h: 0.3, fontSize: 13, bold: true, color: DARK, fontFace: "Calibri", margin: 0 });
  const afterSteps = [
    "Go to Settings → Card Template & Text Layout",
    "Select your uploaded template as the Active Template",
    "Use the visual preview to click on the card image and find X/Y coordinates for each text field",
    "Adjust position, font size and colour for: Member Name, Father Name, Sport, Member ID, Expiry Date, Member Photo",
    "Click Save Settings",
  ];
  s.addText(afterSteps.map(t => ({ text: t, options: { bullet: true, breakLine: true } })).map((o, i) => i === afterSteps.length-1 ? {...o, options:{...o.options, breakLine:false}} : o), {
    x: 0.75, y: 3.88, w: 8.5, h: 1.3,
    fontSize: 11, color: MUTED, fontFace: "Calibri", margin: 0,
  });
}

// ══════════════════════════════════════════════════════════════════════════════
// SLIDE 4 — Settings (text overlay)
// ══════════════════════════════════════════════════════════════════════════════
{
  const s = pres.addSlide();
  lightSlide(s);
  addHeader(s, "02 — Settings: Text Overlay & Card Layout", "Personalized Cards → Settings");

  // Left column: fields table
  s.addText("CONFIGURABLE FIELDS", {
    x: 0.5, y: 1.35, w: 4.5, h: 0.25,
    fontSize: 9.5, bold: true, color: TEAL, charSpacing: 2, fontFace: "Calibri", margin: 0,
  });

  const fields = [
    ["Member Name",  "Name printed on the card"],
    ["Father Name",  "Father's name"],
    ["Sport",        "Member's sport/discipline"],
    ["Member ID",    "Custom or WP user ID"],
    ["Expiry Date",  "Format configurable (e.g. d/m/Y)"],
    ["Member Photo", "X, Y, Width, Height"],
    ["QR Code",      "Reserved — disabled for now"],
  ];

  const hdrColors = ["F4F7FB", WHITE];
  s.addShape(pres.shapes.RECTANGLE, { x: 0.5, y: 1.65, w: 4.5, h: 0.35, fill: { color: TEAL }, line: { color: TEAL } });
  s.addText([
    { text: "Field", options: { bold: true, color: WHITE } },
  ], { x: 0.55, y: 1.65, w: 2.2, h: 0.35, fontSize: 11, fontFace: "Calibri", valign: "middle", margin: 0 });
  s.addText([
    { text: "Description", options: { bold: true, color: WHITE } },
  ], { x: 2.8, y: 1.65, w: 2.1, h: 0.35, fontSize: 11, fontFace: "Calibri", valign: "middle", margin: 0 });

  fields.forEach((f, i) => {
    const ry = 2.0 + i * 0.38;
    const bg = i % 2 === 0 ? "F4F7FB" : WHITE;
    s.addShape(pres.shapes.RECTANGLE, { x: 0.5, y: ry, w: 4.5, h: 0.38, fill: { color: bg }, line: { color: GRAY, width: 0.5 } });
    s.addText(f[0], { x: 0.6, y: ry + 0.04, w: 2.1, h: 0.3, fontSize: 10.5, bold: true, color: DARK, fontFace: "Calibri", margin: 0 });
    s.addText(f[1], { x: 2.8, y: ry + 0.04, w: 2.1, h: 0.3, fontSize: 10.5, color: MUTED, fontFace: "Calibri", margin: 0 });
  });

  // Right column: tips
  s.addText("TIPS", {
    x: 5.3, y: 1.35, w: 4.2, h: 0.25,
    fontSize: 9.5, bold: true, color: TEAL, charSpacing: 2, fontFace: "Calibri", margin: 0,
  });

  const tips = [
    { t: "Click the preview image", d: "Click anywhere on the template preview to get exact pixel coordinates (X, Y) for that spot." },
    { t: "Scale awareness", d: "The preview is scaled down. Coordinates are automatically converted to actual pixel values." },
    { t: "Expiry format", d: "Use PHP date format strings: d/m/Y → 31/12/2027 · F j, Y → December 31, 2027" },
    { t: "Change template live", d: "Changing the Active Template dropdown instantly updates the preview — no save needed." },
  ];

  tips.forEach((tip, i) => {
    const ty = 1.65 + i * 0.95;
    s.addShape(pres.shapes.RECTANGLE, { x: 5.3, y: ty, w: 4.2, h: 0.88, fill: { color: WHITE }, line: { color: GRAY, width: 1 }, shadow: makeShadow() });
    s.addShape(pres.shapes.RECTANGLE, { x: 5.3, y: ty, w: 0.06, h: 0.88, fill: { color: ORANGE }, line: { color: ORANGE } });
    s.addText(tip.t, { x: 5.45, y: ty + 0.08, w: 3.9, h: 0.28, fontSize: 12, bold: true, color: DARK, fontFace: "Calibri", margin: 0 });
    s.addText(tip.d, { x: 5.45, y: ty + 0.38, w: 3.9, h: 0.42, fontSize: 10.5, color: MUTED, fontFace: "Calibri", margin: 0 });
  });
}

// ══════════════════════════════════════════════════════════════════════════════
// SLIDE 5 — Managing Members
// ══════════════════════════════════════════════════════════════════════════════
{
  const s = pres.addSlide();
  lightSlide(s);
  addHeader(s, "03 — Managing Members", "Users → Edit User → Personalized Cards section");

  s.addText("INDIVIDUAL MEMBER SETUP", {
    x: 0.5, y: 1.35, w: 9, h: 0.25,
    fontSize: 9.5, bold: true, color: TEAL, charSpacing: 2, fontFace: "Calibri", margin: 0,
  });

  const mCards = [
    { title: "Activate Membership",  body: "Check 'Active Member'. The member gains access to the My Card page and their card can be created." },
    { title: "Set Expiry Date",      body: "Pick any custom date in the Expiry Date field. A renewal confirmation email is sent automatically when the date changes." },
    { title: "Father Name",          body: "Stored as user meta. Used on the card if not overridden at card-creation time." },
    { title: "Sport",                body: "The member's sport or discipline, printed on the card." },
    { title: "Member ID",            body: "Custom ID shown on the card. Defaults to WP user ID if left blank." },
    { title: "Member Photo",         body: "URL of the member's photo. Use the Choose Image button to pick from the Media Library." },
  ];

  const perRow = 3;
  mCards.forEach((c, i) => {
    const col = i % perRow;
    const row = Math.floor(i / perRow);
    card(s, 0.5 + col * 3.08, 1.68 + row * 1.75, 2.9, 1.6, c.title, c.body, i < 2 ? TEAL : ORANGE);
  });
}

// ══════════════════════════════════════════════════════════════════════════════
// SLIDE 6 — CSV Bulk Import
// ══════════════════════════════════════════════════════════════════════════════
{
  const s = pres.addSlide();
  lightSlide(s);
  addHeader(s, "03 — CSV Bulk Import", "Personalized Cards → Import Users");

  // Left: columns list
  s.addText("CSV COLUMNS", {
    x: 0.5, y: 1.35, w: 4.5, h: 0.25,
    fontSize: 9.5, bold: true, color: TEAL, charSpacing: 2, fontFace: "Calibri", margin: 0,
  });

  const csvCols = [
    { col: "email",        req: true,  desc: "User email address" },
    { col: "display_name", req: false, desc: "Full name shown on site" },
    { col: "first_name",   req: false, desc: "First name" },
    { col: "last_name",    req: false, desc: "Last name" },
    { col: "father_name",  req: false, desc: "Father's name for card" },
    { col: "sport",        req: false, desc: "Sport / discipline" },
    { col: "member_id",    req: false, desc: "Custom member ID" },
    { col: "expiry_date",  req: false, desc: "Format: YYYY-MM-DD" },
    { col: "member_image", req: false, desc: "Photo URL" },
    { col: "password",     req: false, desc: "Auto-generated if blank" },
  ];

  s.addShape(pres.shapes.RECTANGLE, { x: 0.5, y: 1.63, w: 4.5, h: 0.32, fill: { color: TEAL }, line: { color: TEAL } });
  s.addText("Column", { x: 0.56, y: 1.63, w: 1.6, h: 0.32, fontSize: 10.5, bold: true, color: WHITE, fontFace: "Calibri", valign: "middle", margin: 0 });
  s.addText("Required", { x: 2.2, y: 1.63, w: 0.9, h: 0.32, fontSize: 10.5, bold: true, color: WHITE, fontFace: "Calibri", valign: "middle", margin: 0 });
  s.addText("Description", { x: 3.15, y: 1.63, w: 1.75, h: 0.32, fontSize: 10.5, bold: true, color: WHITE, fontFace: "Calibri", valign: "middle", margin: 0 });

  csvCols.forEach((c, i) => {
    const ry = 1.95 + i * 0.32;
    const bg = i % 2 === 0 ? "F4F7FB" : WHITE;
    s.addShape(pres.shapes.RECTANGLE, { x: 0.5, y: ry, w: 4.5, h: 0.32, fill: { color: bg }, line: { color: GRAY, width: 0.5 } });
    s.addText(c.col, { x: 0.6, y: ry + 0.05, w: 1.55, h: 0.22, fontSize: 10, color: DARK, fontFace: "Consolas", bold: true, margin: 0 });
    s.addText(c.req ? "✓" : "—", { x: 2.3, y: ry + 0.05, w: 0.8, h: 0.22, fontSize: 10, color: c.req ? TEAL : MUTED, bold: c.req, align: "center", fontFace: "Calibri", margin: 0 });
    s.addText(c.desc, { x: 3.15, y: ry + 0.05, w: 1.75, h: 0.22, fontSize: 10, color: MUTED, fontFace: "Calibri", margin: 0 });
  });

  // Right: process steps
  s.addText("HOW IT WORKS", {
    x: 5.3, y: 1.35, w: 4.2, h: 0.25,
    fontSize: 9.5, bold: true, color: TEAL, charSpacing: 2, fontFace: "Calibri", margin: 0,
  });

  const importSteps = [
    ["Prepare CSV",        "First row = header. Use the Download Sample CSV button to get a ready-to-fill template."],
    ["Choose options",     "Check 'Activate Membership' to mark all imported users as active. Optionally send a WP welcome email."],
    ["Upload & Import",    "Select your .csv file and click Import. The system processes each row one by one."],
    ["Review results",     "A per-row table shows: Created, Updated, Skipped, or Error with details for each email address."],
  ];

  importSteps.forEach(([title, desc], i) => {
    step(s, 5.3, 1.65 + i * 0.88, i + 1, title, desc);
  });

  // Options note
  s.addShape(pres.shapes.RECTANGLE, { x: 5.3, y: 5.1, w: 4.2, h: 0.38, fill: { color: "FFF3E0" }, line: { color: "FFB74D", width: 1 } });
  s.addText("Existing users (matched by email) have their meta updated — they are not duplicated.", {
    x: 5.45, y: 5.14, w: 4.0, h: 0.3, fontSize: 10, color: "7B5E00", fontFace: "Calibri", margin: 0,
  });
}

// ══════════════════════════════════════════════════════════════════════════════
// SLIDE 7 — Creating Cards
// ══════════════════════════════════════════════════════════════════════════════
{
  const s = pres.addSlide();
  lightSlide(s);
  addHeader(s, "04 — Creating Cards", "Personalized Cards → Dashboard");

  const methods = [
    {
      title: "Create Single Card",
      color: TEAL,
      steps: [
        "Select a member from the dropdown (only active members shown)",
        "Optionally override: Name, Father Name, Sport, Member ID, Photo URL",
        "Tick 'Send Email' to email the card immediately",
        "Click Create Card — front + back are generated automatically",
      ],
    },
    {
      title: "Bulk Create & Email All",
      color: ORANGE,
      steps: [
        "Click 'Create & Email All Active Members'",
        "Members without a card get one created",
        "All active members receive their card by email",
        "Skipped count shows members who already had a card",
      ],
    },
    {
      title: "Re-email Existing Cards",
      color: NAVY,
      steps: [
        "Click 'Re-email Existing Cards' in the Bulk Actions section",
        "Sends emails only — does not create new cards",
        "Useful after template or content changes",
        "Front and back are both attached to the email",
      ],
    },
  ];

  methods.forEach((m, i) => {
    const x = 0.5 + i * 3.08;
    const y = 1.35;
    s.addShape(pres.shapes.RECTANGLE, { x, y, w: 2.9, h: 3.9, fill: { color: WHITE }, line: { color: GRAY, width: 1 }, shadow: makeShadow() });
    s.addShape(pres.shapes.RECTANGLE, { x, y, w: 2.9, h: 0.45, fill: { color: m.color }, line: { color: m.color } });
    s.addText(m.title, { x: x + 0.12, y: y + 0.08, w: 2.7, h: 0.3, fontSize: 12.5, bold: true, color: WHITE, fontFace: "Calibri", margin: 0 });
    m.steps.forEach((step, si) => {
      s.addText([{ text: step, options: { bullet: true, breakLine: si < m.steps.length - 1 } }], {
        x: x + 0.15, y: y + 0.55 + si * 0.74, w: 2.65, h: 0.65,
        fontSize: 10.5, color: MUTED, fontFace: "Calibri", margin: 0,
      });
    });
  });

  // Edit card note
  s.addShape(pres.shapes.RECTANGLE, { x: 0.5, y: 5.35, w: 9, h: 0.15, fill: { color: LIGHT }, line: { color: GRAY } });
  s.addText("Edit Card: In All Cards or Recent Cards, click Edit on any row → adjust fields → optionally regenerate the image → Save.", {
    x: 0.6, y: 5.38, w: 8.8, h: 0.1, fontSize: 9.5, color: MUTED, fontFace: "Calibri", margin: 0,
  });
}

// ══════════════════════════════════════════════════════════════════════════════
// SLIDE 8 — Member Frontend
// ══════════════════════════════════════════════════════════════════════════════
{
  const s = pres.addSlide();
  lightSlide(s);
  addHeader(s, "05 — Member Frontend", "The [pc_my_card] shortcode");

  // Shortcode highlight
  s.addShape(pres.shapes.RECTANGLE, { x: 0.5, y: 1.35, w: 9, h: 0.5, fill: { color: NAVY }, line: { color: NAVY } });
  s.addText("[pc_my_card]  ·  [pc_login]", {
    x: 0.7, y: 1.35, w: 8.6, h: 0.5,
    fontSize: 16, bold: true, color: ICE, fontFace: "Consolas", valign: "middle", margin: 0,
  });

  s.addText("These shortcodes are automatically added to two pages created on plugin activation: My Card and Member Login.", {
    x: 0.5, y: 1.95, w: 9, h: 0.35,
    fontSize: 11.5, color: MUTED, fontFace: "Calibri", margin: 0, italic: true,
  });

  const features = [
    { title: "Front Card Image",    desc: "Member sees their latest card at full width with a download button." },
    { title: "Back Card Image",     desc: "If a back template is configured, it is displayed below the front." },
    { title: "Expiry Notice",       desc: "Shows days remaining. Turns to 'EXPIRED' overlay when past the date." },
    { title: "Download Button",     desc: "Disabled automatically when membership is expired." },
    { title: "Google Wallet",       desc: "Add to Google Wallet button appears when enabled in Settings." },
    { title: "Previous Cards",      desc: "If the member has multiple cards, older ones appear in a grid below." },
    { title: "Auto Deactivation",   desc: "If a member visits after expiry, their status is set to inactive silently." },
    { title: "Login Redirect",      desc: "Non-logged-in users are redirected to the Member Login page automatically." },
  ];

  const perCol = 4;
  features.forEach((f, i) => {
    const col = Math.floor(i / perCol);
    const row = i % perCol;
    const x = 0.5 + col * 4.75;
    const y = 2.42 + row * 0.74;
    s.addShape(pres.shapes.RECTANGLE, { x, y, w: 4.4, h: 0.65, fill: { color: WHITE }, line: { color: GRAY, width: 1 }, shadow: makeShadow() });
    s.addShape(pres.shapes.RECTANGLE, { x, y, w: 0.06, h: 0.65, fill: { color: TEAL }, line: { color: TEAL } });
    s.addText(f.title, { x: x + 0.18, y: y + 0.06, w: 4.1, h: 0.25, fontSize: 11.5, bold: true, color: DARK, fontFace: "Calibri", margin: 0 });
    s.addText(f.desc, { x: x + 0.18, y: y + 0.33, w: 4.1, h: 0.26, fontSize: 10.5, color: MUTED, fontFace: "Calibri", margin: 0 });
  });
}

// ══════════════════════════════════════════════════════════════════════════════
// SLIDE 9 — Email System
// ══════════════════════════════════════════════════════════════════════════════
{
  const s = pres.addSlide();
  lightSlide(s);
  addHeader(s, "06 — Email System", "Three automated emails keep members informed");

  const emails = [
    {
      title: "Card Delivery Email",
      when: "Triggered manually",
      color: TEAL,
      points: [
        "Sent when creating a card (if 'Send Email' is ticked)",
        "Also triggered by Bulk Create & Email or Re-email buttons",
        "Card front + back are both embedded AND attached as files",
        "Google Wallet link included when enabled",
        "From name, from email, subject & body all configurable in Settings",
      ],
    },
    {
      title: "Renewal Confirmation",
      when: "Automatic on expiry date change",
      color: ORANGE,
      points: [
        "Fires when admin saves a member profile with a new expiry date",
        "Only sends if the member is active AND the date actually changed",
        "Tells the member their new expiry date clearly",
        "Logged to the Activity Log as 'renewal_confirmation_sent'",
      ],
    },
    {
      title: "30-Day Reminder",
      when: "Automatic — daily cron",
      color: NAVY,
      points: [
        "Runs every night at midnight automatically",
        "Finds members whose expiry is exactly 30 days away",
        "Sends a friendly reminder to contact admin for renewal",
        "Each member receives this only once (tracked per user)",
        "Also auto-deactivates members whose expiry has passed",
      ],
    },
  ];

  emails.forEach((e, i) => {
    const x = 0.5 + i * 3.08;
    s.addShape(pres.shapes.RECTANGLE, { x, y: 1.35, w: 2.9, h: 4.05, fill: { color: WHITE }, line: { color: GRAY, width: 1 }, shadow: makeShadow() });
    s.addShape(pres.shapes.RECTANGLE, { x, y: 1.35, w: 2.9, h: 0.55, fill: { color: e.color }, line: { color: e.color } });
    s.addText(e.title, { x: x + 0.12, y: 1.38, w: 2.7, h: 0.28, fontSize: 12, bold: true, color: WHITE, fontFace: "Calibri", margin: 0 });
    badge(s, x + 0.12, 1.68, e.when.length < 18 ? e.when : e.when, "FFFFFF");
    // Override badge text color to dark
    s.addShape(pres.shapes.RECTANGLE, { x: x + 0.12, y: 1.66, w: 2.65, h: 0.28, fill: { color: "00000000" === "00000000" ? "FFFFFF" : WHITE }, line: { color: "E2E8F0", width: 1 } });
    s.addText(e.when, { x: x + 0.12, y: 1.66, w: 2.65, h: 0.28, fontSize: 9.5, color: e.color, bold: true, fontFace: "Calibri", valign: "middle", align: "center", margin: 0 });

    e.points.forEach((p, pi) => {
      s.addText([{ text: p, options: { bullet: true, breakLine: pi < e.points.length - 1 } }], {
        x: x + 0.15, y: 2.02 + pi * 0.62, w: 2.65, h: 0.55,
        fontSize: 10.5, color: MUTED, fontFace: "Calibri", margin: 0,
      });
    });
  });
}

// ══════════════════════════════════════════════════════════════════════════════
// SLIDE 10 — Activity Log
// ══════════════════════════════════════════════════════════════════════════════
{
  const s = pres.addSlide();
  lightSlide(s);
  addHeader(s, "07 — Activity Log", "Personalized Cards → Activity Log");

  s.addText("Every important action is recorded here with timestamp, user and details.", {
    x: 0.5, y: 1.35, w: 9, h: 0.3,
    fontSize: 12, color: MUTED, fontFace: "Calibri", italic: true, margin: 0,
  });

  const logTypes = [
    { action: "card_created",           color: "46B450", label: "Card Created",           desc: "A new card was generated for a member" },
    { action: "card_emailed",           color: "0073AA", label: "Card Emailed",            desc: "A card was sent by email" },
    { action: "card_edited",            color: "F0A500", label: "Card Edited",             desc: "Card data or image was updated via Edit" },
    { action: "card_deleted",           color: "DC3232", label: "Card Deleted",            desc: "A card record was removed" },
    { action: "membership_expired",     color: "DC3232", label: "Membership Expired",      desc: "Auto-deactivated by the nightly cron" },
    { action: "renewal_reminder_sent",  color: "F0A500", label: "Renewal Reminder Sent",  desc: "30-day reminder email was dispatched" },
    { action: "renewal_confirmation_sent", color: "46B450", label: "Renewal Confirmed",   desc: "Expiry date updated and member notified" },
    { action: "csv_import",             color: "0073AA", label: "CSV Import",              desc: "A bulk import was completed" },
  ];

  s.addShape(pres.shapes.RECTANGLE, { x: 0.5, y: 1.72, w: 9, h: 0.35, fill: { color: TEAL }, line: { color: TEAL } });
  s.addText("Action", { x: 0.6, y: 1.72, w: 2.2, h: 0.35, fontSize: 11, bold: true, color: WHITE, fontFace: "Calibri", valign: "middle", margin: 0 });
  s.addText("Description", { x: 2.85, y: 1.72, w: 6.5, h: 0.35, fontSize: 11, bold: true, color: WHITE, fontFace: "Calibri", valign: "middle", margin: 0 });

  logTypes.forEach((lt, i) => {
    const ry = 2.07 + i * 0.38;
    const bg = i % 2 === 0 ? "F4F7FB" : WHITE;
    s.addShape(pres.shapes.RECTANGLE, { x: 0.5, y: ry, w: 9, h: 0.38, fill: { color: bg }, line: { color: GRAY, width: 0.5 } });
    s.addShape(pres.shapes.RECTANGLE, { x: 0.55, y: ry + 0.09, w: 0.12, h: 0.12, fill: { color: lt.color }, line: { color: lt.color } });
    s.addText(lt.label, { x: 0.75, y: ry + 0.05, w: 2.0, h: 0.28, fontSize: 10.5, bold: true, color: DARK, fontFace: "Calibri", margin: 0 });
    s.addText(lt.desc, { x: 2.85, y: ry + 0.05, w: 6.5, h: 0.28, fontSize: 10.5, color: MUTED, fontFace: "Calibri", margin: 0 });
  });

  s.addText("Filter by action type using the dropdown. Use Clear Log to reset. Shows last 200 entries.", {
    x: 0.5, y: 5.15, w: 9, h: 0.28,
    fontSize: 10.5, color: MUTED, fontFace: "Calibri", italic: true, margin: 0,
  });
}

// ══════════════════════════════════════════════════════════════════════════════
// SLIDE 11 — Settings Backup
// ══════════════════════════════════════════════════════════════════════════════
{
  const s = pres.addSlide();
  lightSlide(s);
  addHeader(s, "08 — Settings Export & Import", "Personalized Cards → Settings → bottom of page");

  // Two big cards side by side
  // Export
  s.addShape(pres.shapes.RECTANGLE, { x: 0.5, y: 1.35, w: 4.35, h: 3.9, fill: { color: WHITE }, line: { color: GRAY, width: 1 }, shadow: makeShadow() });
  s.addShape(pres.shapes.RECTANGLE, { x: 0.5, y: 1.35, w: 4.35, h: 0.5, fill: { color: TEAL }, line: { color: TEAL } });
  s.addText("Export Settings", { x: 0.65, y: 1.38, w: 4.1, h: 0.44, fontSize: 15, bold: true, color: WHITE, fontFace: "Calibri", valign: "middle", margin: 0 });

  const exportItems = [
    "All text field positions (X, Y, size, color)",
    "Active template & back template names",
    "Font file selection",
    "Email settings (from, subject, message)",
    "Wallet settings (Google Wallet issuer ID)",
    "QR code configuration",
    "Expiry date format",
  ];
  s.addText("What is exported:", { x: 0.65, y: 1.92, w: 4.05, h: 0.28, fontSize: 11.5, bold: true, color: DARK, fontFace: "Calibri", margin: 0 });
  s.addText(exportItems.map((t, i) => ({ text: t, options: { bullet: true, breakLine: i < exportItems.length - 1 } })), {
    x: 0.65, y: 2.22, w: 4.05, h: 2.2, fontSize: 10.5, color: MUTED, fontFace: "Calibri", margin: 0,
  });
  s.addShape(pres.shapes.RECTANGLE, { x: 0.65, y: 4.5, w: 4.05, h: 0.6, fill: { color: "EBF8F5" }, line: { color: ICE, width: 1 } });
  s.addText("Does NOT export template/font/certificate files — only the configuration values.", {
    x: 0.75, y: 4.55, w: 3.85, h: 0.5, fontSize: 10, color: TEAL, fontFace: "Calibri", margin: 0,
  });

  // Import
  s.addShape(pres.shapes.RECTANGLE, { x: 5.15, y: 1.35, w: 4.35, h: 3.9, fill: { color: WHITE }, line: { color: GRAY, width: 1 }, shadow: makeShadow() });
  s.addShape(pres.shapes.RECTANGLE, { x: 5.15, y: 1.35, w: 4.35, h: 0.5, fill: { color: ORANGE }, line: { color: ORANGE } });
  s.addText("Import Settings", { x: 5.3, y: 1.38, w: 4.1, h: 0.44, fontSize: 15, bold: true, color: WHITE, fontFace: "Calibri", valign: "middle", margin: 0 });

  const importSteps2 = [
    ["Step 1", "Export settings from your source site (staging or old install)"],
    ["Step 2", "Go to Settings on the target site"],
    ["Step 3", "Scroll to 'Import Settings', choose the .json file"],
    ["Step 4", "Click Import Settings — all configuration is restored instantly"],
  ];
  importSteps2.forEach(([label, desc], i) => {
    const iy = 1.95 + i * 0.82;
    s.addShape(pres.shapes.RECTANGLE, { x: 5.3, y: iy, w: 0.52, h: 0.28, fill: { color: ORANGE }, line: { color: ORANGE } });
    s.addText(label, { x: 5.3, y: iy, w: 0.52, h: 0.28, fontSize: 9.5, bold: true, color: WHITE, align: "center", valign: "middle", fontFace: "Calibri", margin: 0 });
    s.addText(desc, { x: 5.9, y: iy + 0.01, w: 3.5, h: 0.28, fontSize: 10.5, color: DARK, fontFace: "Calibri", margin: 0 });
    s.addText("", { x: 5.3, y: iy + 0.32, w: 3.95, h: 0.42, fontSize: 10.5, color: MUTED, fontFace: "Calibri", margin: 0 });
  });
  s.addShape(pres.shapes.RECTANGLE, { x: 5.3, y: 4.5, w: 4.05, h: 0.6, fill: { color: "FFF3E0" }, line: { color: "FFB74D", width: 1 } });
  s.addText("Existing settings on the target site are overwritten. Template & font files must be re-uploaded separately.", {
    x: 5.4, y: 4.55, w: 3.85, h: 0.5, fontSize: 10, color: "7B5E00", fontFace: "Calibri", margin: 0,
  });
}

// ══════════════════════════════════════════════════════════════════════════════
// SLIDE 12 — Quick Reference
// ══════════════════════════════════════════════════════════════════════════════
{
  const s = pres.addSlide();
  darkSlide(s);

  s.addShape(pres.shapes.RECTANGLE, { x: 0, y: 0, w: 0.22, h: 5.625, fill: { color: TEAL }, line: { color: TEAL } });

  s.addText("Quick Reference", {
    x: 0.5, y: 0.25, w: 9, h: 0.6,
    fontSize: 30, bold: true, color: WHITE, fontFace: "Calibri", margin: 0,
  });

  const refs = [
    { label: "Upload template",         where: "Settings → Upload Card Template" },
    { label: "Upload back template",    where: "Settings → Upload Card Back Template" },
    { label: "Set field positions",     where: "Settings → Card Template & Text Layout (click preview)" },
    { label: "Activate a member",       where: "Users → Edit User → Active Member ✓ + Expiry Date" },
    { label: "Create one card",         where: "Dashboard → Create Card for Member" },
    { label: "Create all cards",        where: "Dashboard → Bulk Actions → Create & Email All" },
    { label: "Edit a card",             where: "Dashboard / All Cards → Edit button" },
    { label: "Import users from CSV",   where: "Import Users → Upload CSV" },
    { label: "View activity",           where: "Activity Log" },
    { label: "Backup settings",         where: "Settings → Export / Import Settings" },
  ];

  const half = Math.ceil(refs.length / 2);
  refs.forEach((r, i) => {
    const col = Math.floor(i / half);
    const row = i % half;
    const x = 0.5 + col * 4.75;
    const y = 0.97 + row * 0.46;
    s.addShape(pres.shapes.RECTANGLE, { x, y, w: 4.4, h: 0.38, fill: { color: "1E2F6A" }, line: { color: "2A3E88", width: 1 } });
    s.addShape(pres.shapes.RECTANGLE, { x, y, w: 0.06, h: 0.38, fill: { color: TEAL }, line: { color: TEAL } });
    s.addText(r.label, { x: x + 0.16, y: y + 0.04, w: 4.1, h: 0.18, fontSize: 10.5, bold: true, color: ICE, fontFace: "Calibri", margin: 0 });
    s.addText(r.where, { x: x + 0.16, y: y + 0.2, w: 4.1, h: 0.15, fontSize: 9.5, color: "7A9CC0", fontFace: "Calibri", margin: 0 });
  });

  s.addShape(pres.shapes.RECTANGLE, { x: 0, y: 5.2, w: 10, h: 0.425, fill: { color: "0F1A40" }, line: { color: "0F1A40" } });
  s.addText("Personalized Cards Plugin  ·  User Manual", {
    x: 0.3, y: 5.21, w: 9.4, h: 0.4,
    fontSize: 10, color: "6B88A8", fontFace: "Calibri", align: "center", margin: 0,
  });
}

// ── Write file ─────────────────────────────────────────────────────────────────
pres.writeFile({ fileName: "Personalized-Cards-User-Manual.pptx" }).then(() => {
  console.log("Done: Personalized-Cards-User-Manual.pptx");
}).catch(e => console.error(e));
