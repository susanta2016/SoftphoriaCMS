// A synthetic 9:16 sample creative, drawn in the browser (no bundled asset, no
// third-party content). The headline sits deliberately in the caption area and
// the logo under the action buttons, so the sample shows real findings.
export async function sampleFile() {
    const c = document.createElement('canvas');
    c.width = 1080;
    c.height = 1920;
    const ctx = c.getContext('2d');
    const g = ctx.createLinearGradient(0, 0, 0, 1920);
    g.addColorStop(0, '#1f3b73');
    g.addColorStop(0.55, '#3d6fb6');
    g.addColorStop(1, '#0f1d38');
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, 1080, 1920);
    // soft "photo" shapes
    for (const [x, y, r, a] of [[260, 700, 320, 0.18], [820, 1050, 380, 0.14], [540, 380, 260, 0.12]]) {
        const rg = ctx.createRadialGradient(x, y, 0, x, y, r);
        rg.addColorStop(0, `rgba(255,255,255,${a})`);
        rg.addColorStop(1, 'rgba(255,255,255,0)');
        ctx.fillStyle = rg;
        ctx.fillRect(0, 0, 1080, 1920);
    }
    ctx.fillStyle = '#ffffff';
    ctx.textBaseline = 'top';
    ctx.font = '700 64px system-ui, sans-serif';
    ctx.fillText('Spring sale', 120, 760);
    ctx.font = '500 40px system-ui, sans-serif';
    ctx.fillText('New colours, same comfort.', 120, 850);
    // logo: circle with initials, under the action column
    ctx.beginPath();
    ctx.arc(960, 1300, 70, 0, Math.PI * 2);
    ctx.fillStyle = '#ffd23f';
    ctx.fill();
    ctx.fillStyle = '#1f3b73';
    ctx.font = '800 56px system-ui, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText('SP', 960, 1270);
    ctx.textAlign = 'left';
    // headline in the caption area
    ctx.fillStyle = '#ffffff';
    ctx.font = '800 76px system-ui, sans-serif';
    ctx.fillText('Shop now: 30% off', 110, 1690);
    const blob = await new Promise((resolve) => c.toBlob(resolve, 'image/png'));
    return new File([blob], 'sample-creative.png', { type: 'image/png' });
}
