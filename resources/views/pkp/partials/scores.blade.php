@if ($scoreData === null)
    <p class="alert alert-info" role="status">Nilai kinerja belum tersedia karena draf belum lengkap.</p>
@else
    <section class="card mb-4" aria-labelledby="pkp-score-heading">
        <div class="card-body">
            <h2 id="pkp-score-heading" class="h4">Hasil perhitungan kinerja</h2>
            <p>
                Nilai keseluruhan:
                <strong>{{ $scoreData['overall_score']['display'] }}</strong>
                · Predikat: <strong>{{ $scoreData['predicate'] }}</strong>
            </p>
            <details>
                <summary>Nilai rasional eksak keseluruhan</summary>
                <code>{{ $scoreData['overall_score']['numerator'] }}/{{ $scoreData['overall_score']['denominator'] }}</code>
            </details>

            @foreach ($scoreData['indicators'] as $indicator)
                <section class="mt-3" aria-label="Indikator {{ $indicator['title'] }}">
                    <h3 class="h5">{{ $indicator['title'] }}</h3>
                    <p>Nilai indikator: {{ $indicator['score']['display'] }}</p>
                    <details>
                        <summary>Nilai rasional eksak indikator</summary>
                        <code>{{ $indicator['score']['numerator'] }}/{{ $indicator['score']['denominator'] }}</code>
                    </details>
                    <ul>
                        @foreach ($indicator['activities'] as $activity)
                            <li>
                                {{ $activity['description'] }}:
                                {{ $activity['achievement']['display'] }}
                                    <details>
                                        <summary>Nilai rasional eksak aktivitas</summary>
                                        <code>{{ $activity['achievement']['numerator'] }}/{{ $activity['achievement']['denominator'] }}</code>
                                    </details>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>
    </section>
@endif
