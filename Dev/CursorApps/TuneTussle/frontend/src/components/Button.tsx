import React from 'react';
import LoadingSpinner from './LoadingSpinner';

export type ButtonVariant = 'primary' | 'secondary' | 'danger';

type ButtonProps = React.ButtonHTMLAttributes<HTMLButtonElement> & {
  label?: string;
  variant?: ButtonVariant;
  isLoading?: boolean;
  children?: React.ReactNode;
};

const Button: React.FC<ButtonProps> = ({
  label,
  onClick,
  type = 'button',
  disabled = false,
  variant = 'primary',
  isLoading = false,
  children,
  className = '',
  ...props
}) => {
  // Base styles - minimal and clean
  const baseClasses = 'px-4 py-2 border font-medium focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed';

  // Simple variant styles
  let variantClasses = '';
  switch (variant) {
    case 'primary':
      variantClasses = 'bg-blue-600 text-white border-blue-600 hover:bg-blue-700 hover:border-blue-700';
      break;
    case 'secondary':
      variantClasses = 'bg-white text-gray-900 border-gray-300 hover:bg-gray-50';
      break;
    case 'danger':
      variantClasses = 'bg-red-600 text-white border-red-600 hover:bg-red-700 hover:border-red-700';
      break;
  }

  return (
    <button
      type={type}
      onClick={onClick}
      disabled={disabled || isLoading}
      className={`${baseClasses} ${variantClasses} ${className}`}
      {...props}
    >
      {isLoading && <LoadingSpinner size="sm" className="mr-2" />}
      {label}
      {children}
    </button>
  );
};

export default Button;