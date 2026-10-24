export interface SetupStep {
    key: string;
    label: string;
}

export interface SetupStepProps {
    steps: SetupStep[];
    current: string;
}

export interface TermInput {
    name: string;
    starts_on: string;
    ends_on: string;
}

export interface ClassInput {
    name: string;
    section: string | null;
}

export interface SubjectInput {
    name: string;
    code: string;
}

export interface GradeBand {
    label: string;
    min_percent: string;
    max_percent: string;
}
