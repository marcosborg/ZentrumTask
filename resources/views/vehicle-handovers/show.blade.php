<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Procedimento #{{ $procedure->id }}</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f3f6fb; color: #16233b; margin: 0; padding: 24px; }
        .handover-doc { max-width: 1080px; margin: 0 auto; background: #fff; border-radius: 20px; padding: 28px; box-shadow: 0 20px 50px rgba(15, 23, 42, 0.08); }
        .handover-doc__hero { display: flex; justify-content: space-between; gap: 24px; margin-bottom: 28px; }
        .handover-doc__eyebrow { text-transform: uppercase; letter-spacing: 0.08em; font-size: 12px; color: #5674b9; margin-bottom: 8px; }
        .handover-doc__hero h1 { margin: 0 0 8px; font-size: 30px; }
        .handover-doc__hero p { margin: 0; color: #5d6b84; }
        .handover-doc__meta { min-width: 300px; display: grid; gap: 10px; }
        .handover-doc__meta div { background: #f8fbff; border: 1px solid #dce7f5; border-radius: 14px; padding: 12px 14px; }
        .handover-doc__meta strong, .handover-doc__meta span { display: block; }
        .handover-doc__meta strong { font-size: 12px; color: #5f6f8f; margin-bottom: 4px; }
        .handover-doc__grid { display: grid; grid-template-columns: 1.2fr 1fr; gap: 24px; margin-bottom: 28px; }
        .handover-doc__table { width: 100%; border-collapse: collapse; }
        .handover-doc__table td { border-bottom: 1px solid #e5edf8; padding: 10px 0; font-size: 14px; vertical-align: top; }
        .handover-doc__stack { display: grid; gap: 14px; }
        .handover-doc__damage { border: 1px solid #dce7f5; border-radius: 14px; padding: 14px; background: #fbfdff; }
        .handover-doc__damage-head { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 8px; }
        .handover-doc__muted { color: #64748b; }
        .handover-doc__photos { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; }
        .handover-doc__photo { width: 100%; border-radius: 14px; border: 1px solid #dce7f5; object-fit: cover; }
        .handover-doc__signatures { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-top: 28px; }
        .handover-doc__signature { width: 100%; min-height: 140px; border: 1px dashed #cbd5e1; border-radius: 14px; background: #fff; }
        h2 { font-size: 18px; margin: 0 0 12px; }
        section + section { margin-top: 24px; }
    </style>
</head>
<body>
    @include('vehicle-handovers.partials.procedure-content', ['procedure' => $procedure, 'typeLabels' => $typeLabels])
</body>
</html>
