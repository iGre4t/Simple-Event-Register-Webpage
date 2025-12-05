<?php
$rounds = [
    [
        'label' => 'Round One',
        'matches' => [
            ['teamA' => 'FireVanguard', 'teamB' => 'SteelCloak'],
            ['teamA' => 'NovaRider', 'teamB' => 'SilentFang'],
            ['teamA' => 'BlazeShade', 'teamB' => 'WindSpeak'],
            ['teamA' => 'ObsidianWing', 'teamB' => 'FrostPulse'],
            ['teamA' => 'CrimsonTone', 'teamB' => 'HarborMist'],
            ['teamA' => 'PhantomReef', 'teamB' => 'IronBasilisk'],
            ['teamA' => 'ShadowTide', 'teamB' => 'GlacialReign'],
            ['teamA' => 'SolarKnight', 'teamB' => 'ThunderVale'],
        ],
    ],
    [
        'label' => 'Quarterfinals',
        'matches' => [
            ['teamA' => 'Winner Match 1', 'teamB' => 'Winner Match 2'],
            ['teamA' => 'Winner Match 3', 'teamB' => 'Winner Match 4'],
            ['teamA' => 'Winner Match 5', 'teamB' => 'Winner Match 6'],
            ['teamA' => 'Winner Match 7', 'teamB' => 'Winner Match 8'],
        ],
    ],
    [
        'label' => 'Semifinals',
        'matches' => [
            ['teamA' => 'Winner Quarter 1', 'teamB' => 'Winner Quarter 2'],
            ['teamA' => 'Winner Quarter 3', 'teamB' => 'Winner Quarter 4'],
        ],
    ],
    [
        'label' => 'Grand Final',
        'matches' => [
            ['teamA' => 'Winner Semi 1', 'teamB' => 'Winner Semi 2'],
        ],
    ],
];

$roundCount = count($rounds);
$slotUnits = 2 << ($roundCount - 1);

function getMatchRowIndex(int $roundIndex, int $matchIndex): int
{
    $step = 2 << $roundIndex;
    return $matchIndex * $step + ($step / 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Elimination Bracket</title>
    <style>
        @font-face {
            font-family: 'PeydaWebFaNum';
            src: url('fonts/PeydaWebFaNum-Regular.woff2') format('woff2');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }

        @font-face {
            font-family: 'PeydaWebFaNum';
            src: url('fonts/PeydaWebFaNum-Bold.woff2') format('woff2');
            font-weight: 700;
            font-style: normal;
            font-display: swap;
        }

        :root {
            --brand: #C63437;
            --bg: #030409;
            --card: rgba(255, 255, 255, 0.04);
            --border: rgba(255, 255, 255, 0.08);
            --muted: rgba(255, 255, 255, 0.6);
            --font: 'PeydaWebFaNum', sans-serif;
            --slot-step: 100px;
            --slots: <?php echo $slotUnits; ?>;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--bg);
            color: #fff;
            font-family: var(--font);
            display: flex;
            justify-content: flex-start;
            align-items: flex-start;
        }

        .page-shell {
            padding: 2.5rem;
            width: fit-content;
        }

        header {
            margin-bottom: 1rem;
        }

        header h1 {
            margin: 0;
            font-size: 2.2rem;
            font-weight: 700;
        }

        header p {
            margin: 0.25rem 0 0;
            color: var(--muted);
            font-size: 0.95rem;
            max-width: 560px;
        }

        .bracket-board {
            display: flex;
            gap: 2.5rem;
            align-items: flex-start;
            width: auto;
        }

        .round {
            min-width: 250px;
        }

        .round-title {
            font-size: 0.75rem;
            letter-spacing: 0.25em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 0.35rem;
        }

        .round-track {
            position: relative;
            height: calc(var(--slot-step) * var(--slots));
        }

        .match-slot {
            position: absolute;
            left: 0;
            right: 0;
            top: calc(var(--row-index) * var(--slot-step));
            transform: translateY(-50%);
            padding-right: 3rem;
        }

        .match-slot.pair-top::after,
        .match-slot.pair-bottom::before {
            content: "";
            position: absolute;
            right: 1rem;
            width: 1px;
            background: var(--border);
            opacity: 0.8;
        }

        .match-slot.pair-top::after {
            top: 50%;
            height: calc(var(--slot-step) * var(--row-diff));
        }

        .match-slot.pair-bottom::before {
            bottom: 50%;
            height: calc(var(--slot-step) * var(--row-diff));
        }

        .match-slot.round-final-slot::before,
        .match-slot.round-final-slot::after {
            display: none;
        }

        .match-card {
            border-radius: 12px;
            border: 1px solid var(--border);
            background: var(--card);
            padding: 0.9rem 1.1rem;
            min-height: 80px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 0.2rem;
            position: relative;
            overflow: hidden;
        }

        .match-card::after {
            content: "";
            position: absolute;
            right: -3rem;
            top: 50%;
            width: 3rem;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--brand));
            opacity: 0.9;
            transform: translateY(-50%);
        }

        .round:last-child .match-card::after {
            display: none;
        }

        .match-card strong {
            font-size: 1rem;
            font-weight: 700;
        }

        .match-card span {
            font-size: 0.75rem;
            color: var(--muted);
        }

        .status {
            margin-top: 0.25rem;
            font-size: 0.7rem;
            letter-spacing: 0.4em;
            text-transform: uppercase;
            color: var(--muted);
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }

        .status::before {
            content: "";
            width: 0.45rem;
            height: 0.45rem;
            border-radius: 50%;
            background: var(--brand);
        }

        @media (max-width: 900px) {
            body {
                justify-content: center;
            }

            .page-shell {
                padding: 1.5rem;
            }

            .match-card::after {
                display: none;
            }

            .match-slot {
                padding-right: 1rem;
            }
        }
    </style>
</head>
<body>
<div class="page-shell">
    <header>
        <h1>Elimination Bracket</h1>
        <p>Minimal, dark bracket board designed to feel like a cohesive brand page while representing every round's flow.</p>
    </header>

    <div class="bracket-board">
        <?php foreach ($rounds as $roundIndex => $round): ?>
            <div class="round">
                <div class="round-title"><?php echo $round['label']; ?></div>
                <div class="round-track">
                    <?php foreach ($round['matches'] as $matchIndex => $match):
                        $rowIndex = getMatchRowIndex($roundIndex, $matchIndex);
                        $step = 2 << $roundIndex;
                        $isFinalRound = $roundIndex === $roundCount - 1;
                        $pairClass = ($matchIndex % 2 === 0) ? 'pair-top' : 'pair-bottom';
                        $slotClass = trim("match-slot {$pairClass}" . ($isFinalRound ? ' round-final-slot' : ''));
                    ?>
                        <div class="<?php echo $slotClass; ?>"
                             style="--row-index: <?php echo $rowIndex; ?>; --row-diff: <?php echo $step; ?>;">
                            <div class="match-card<?php echo $isFinalRound ? ' round-card-final' : ''; ?>">
                                <strong><?php echo $match['teamA']; ?></strong>
                                <span><?php echo $match['teamB']; ?></span>
                                <div class="status">live</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>
