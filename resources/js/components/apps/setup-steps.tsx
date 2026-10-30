import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

type Step = 'basics' | 'source' | 'settings';

/** The horizontal progress header of the app wizard. */
export function SetupSteps({
    current,
    source,
}: {
    current: Step;
    source: string;
}) {
    const steps: { id: Step; label: string }[] = [
        { id: 'basics', label: 'Basics' },
        {
            id: 'source',
            label: source === 'upload' ? 'Upload code' : 'Repository access',
        },
        { id: 'settings', label: 'Settings' },
    ];
    const index = steps.findIndex((step) => step.id === current);

    return (
        <ol className="mb-6 flex flex-wrap gap-x-6 gap-y-2" aria-label="Steps">
            {steps.map((step, i) => (
                <li
                    key={step.id}
                    aria-current={i === index ? 'step' : undefined}
                    className={cn(
                        'flex items-center gap-2 text-sm',
                        i === index
                            ? 'font-semibold text-base-content'
                            : 'text-base-content/60',
                    )}
                >
                    <span
                        className={cn(
                            'flex size-6 items-center justify-center rounded-full border text-xs',
                            i < index &&
                                'border-success bg-success text-success-content',
                            i === index &&
                                'border-primary bg-primary text-primary-content',
                            i > index && 'border-base-300',
                        )}
                    >
                        {i < index ? (
                            <Check className="size-3.5" aria-hidden />
                        ) : (
                            i + 1
                        )}
                    </span>
                    {step.label}
                </li>
            ))}
        </ol>
    );
}
