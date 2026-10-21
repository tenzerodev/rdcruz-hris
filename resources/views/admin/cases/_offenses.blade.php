<datalist id="offenses">
    @foreach (['Serious dishonesty', 'Less serious dishonesty', 'Simple dishonesty', 'Grave misconduct', 'Simple misconduct', 'Gross neglect of duty', 'Simple neglect of duty',
        'Falsification of official document', 'Habitual absenteeism', 'Habitual tardiness', 'Loafing from duty during regular office hours', 'Conduct prejudicial to the best interest of the service',
        'Discourtesy in the course of official duties', 'Insubordination', 'Violation of reasonable office rules and regulations', 'Sexual harassment', 'Inefficiency and incompetence in the performance of official duties'] as $o)
        <option value="{{ $o }}">
    @endforeach
</datalist>
