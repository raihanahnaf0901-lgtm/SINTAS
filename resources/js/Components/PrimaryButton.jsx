export default function PrimaryButton({
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            className={
                'button-primary ' + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
